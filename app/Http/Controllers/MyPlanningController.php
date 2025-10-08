<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Planning;
use App\Models\PlanningLocationTimer;
use App\Models\User;
use App\Models\EndChecklistItem;
use App\Services\TravelTimeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use App\Models\Benodigdheid;

class MyPlanningController extends Controller
{
    public function __construct(
        private TravelTimeService $travelTimeService
    ) {}

    public function show(?Planning $planning = null)
    {
        /** @var User $user */
        $user = Auth::user();

        // If no planning is provided, find today's planning (original behavior)
        if (!$planning) {
            $today = now()->startOfDay();

            $planning = Planning::where('planned_date', $today)
                ->whereHas('users', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->with(['locations', 'planningTasks.specificLocation', 'planningTasks.task.location', 'planningTasks.task.taskPhotos', 'planningTasks.task.benodigdheden', 'planningTasks.defaultTask.benodigdheden', 'planningTasks.completions.photos'])
                ->first();
        } else {
            // Load necessary relationships for the provided planning
            $planning->load(['locations', 'planningTasks.specificLocation', 'planningTasks.task.location', 'planningTasks.task.taskPhotos', 'planningTasks.task.benodigdheden', 'planningTasks.defaultTask.benodigdheden', 'planningTasks.completions.photos']);

            // Check if user has access to this planning
            if (!$user->isAdmin() && !$planning->users->contains($user)) {
                abort(403, 'Je hebt geen toegang tot deze planning.');
            }
        }

        if (!$planning) {
            return view('my-planning.show-empty');
        }

        $locationSteps = [];

        // Collect all benodigdheden from tasks AND automatically required ones for locations
        $allBenodigdheden = collect();
        $locationSpecificBenodigdheden = collect(); // Track location-specific variants

        // Get benodigdheden from tasks (these don't get location replacement)
        foreach ($planning->planningTasks as $planningTask) {
            // Get benodigdheden from backlog tasks
            if ($planningTask->task && $planningTask->task->benodigdheden) {
                $allBenodigdheden = $allBenodigdheden->merge($planningTask->task->benodigdheden);
            }
            // Get benodigdheden from default tasks
            if ($planningTask->defaultTask && $planningTask->defaultTask->benodigdheden) {
                $allBenodigdheden = $allBenodigdheden->merge($planningTask->defaultTask->benodigdheden);
            }
        }

        // Get automatically required benodigdheden for selected locations
        if (!empty($planning->locations)) {
            foreach ($planning->locations as $location) {
                $automaticBenodigdheden = Benodigdheid::whereHas('requiredForLocations', function ($query) use ($location) {
                    $query->where('location_id', $location->id);
                })->get();

                foreach ($automaticBenodigdheden as $benodigdheid) {
                    if (str_contains($benodigdheid->naam, '[locatie]')) {
                        // Create location-specific variant
                        $locationSpecificBenodigdheden->push((object)[
                            'id' => $benodigdheid->id . '_' . $location->id, // Unique ID for this variant
                            'original_id' => $benodigdheid->id,
                            'naam' => str_replace('[locatie]', $location->name, $benodigdheid->naam),
                            'beschrijving' => $benodigdheid->beschrijving,
                            'location_name' => $location->name,
                            'is_location_specific' => true,
                        ]);
                    } else {
                        // Add as regular benodigdheid (no location replacement needed)
                        $allBenodigdheden->push($benodigdheid);
                    }
                }
            }
        }

        // Merge regular benodigdheden with location-specific variants
        $allBenodigdhedenWithVariants = $allBenodigdheden->concat($locationSpecificBenodigdheden);

        // Remove duplicates (only for regular benodigdheden, keep all location-specific variants)
        $uniqueBenodigdheden = $allBenodigdhedenWithVariants->unique(function ($item) {
            if (isset($item->is_location_specific) && $item->is_location_specific) {
                return $item->id; // Use composite ID for location-specific items
            }
            return $item->id; // Use original ID for regular items
        })->sortBy('naam');

        // Add benodigdheden checklist as first step (only if there are benodigdheden)
        if ($uniqueBenodigdheden->isNotEmpty()) {
            $locationSteps[] = [
                'type' => 'benodigdheden',
                'title' => 'Benodigdheden checklist',
                'details' => 'Controleer of je alle benodigde materialen hebt voordat je begint',
                'benodigdheden' => $uniqueBenodigdheden->map(function ($benodigdheid) {
                    return [
                        'id' => $benodigdheid->id,
                        'naam' => $benodigdheid->naam,
                        'beschrijving' => $benodigdheid->beschrijving,
                    ];
                })->values()->all(),
            ];
        }

        // Add summary as second step
        $locationSteps[] = [
            'type' => 'summary',
            'title' => 'Samenvatting van je dag',
            'details' => 'Bekijk je planning overzicht voordat je begint',
        ];

        // Group tasks by their effective location_id (from planning_task or fallback to parent task)
        $tasksByLocation = $planning->planningTasks->groupBy(function ($planningTask) {
            return $planningTask->location_id ?? $planningTask->task?->location_id;
        });

        // Add tasks with no location (true backlog) first as a "location"
        if (isset($tasksByLocation[''])) {
            $noLocationTasks = $tasksByLocation[''];

            // Partition into backlog vs standard
            [$backlogTasks, $standardTasks] = $noLocationTasks->partition(fn ($pt) => ! is_null($pt->task_id));

            // Sort backlog tasks by priority
            $priorityOrder = [TaskPriority::HIGH->value => 1, TaskPriority::NORMAL->value => 2, TaskPriority::LOW->value => 3];
            $sortedBacklog = $backlogTasks->sortBy(fn ($pt) => $priorityOrder[$pt->task?->priority?->value] ?? 99);

            $sortedTasks = $sortedBacklog->concat($standardTasks);

            $tasksForBacklog = [];
            foreach ($sortedTasks as $task) {
                $latestCompletion = $task->completions->sortByDesc('created_at')->first();
                $backlogPhotos = $task->task && $task->task->taskPhotos ? $task->task->taskPhotos->map(fn($photo) => $photo->url)->toArray() : [];

                // Check if task was skipped - only use skip data if task status is actually 'skipped'
                $skipCompletion = $task->completions->where('review_outcome', 'skipped')->sortByDesc('created_at')->first();
                $isSkipped = $task->status === TaskStatus::SKIPPED;

                $tasksForBacklog[] = [
                    'title' => $task->title,
                    'details' => $task->description,
                    'task_id' => $task->id,
                    'status' => $task->status,
                    'completed_notes' => $latestCompletion->comment ?? null,
                    'photos' => $latestCompletion ? $latestCompletion->photos->pluck('url') : [],
                    'backlog_photos' => $backlogPhotos,
                    'skip_reason' => $isSkipped && $skipCompletion ? $skipCompletion->comment : null,
                    'skip_photos' => $isSkipped && $skipCompletion ? $skipCompletion->photos->pluck('url') : [],
                ];
            }

            if (!empty($tasksForBacklog)) {
                $locationSteps[] = [
                    'type' => 'location',
                    'title' => 'Backlog taken',
                    'location_id' => null,
                    'location_name' => 'Backlog',
                    'address' => null,
                    'tasks' => $tasksForBacklog,
                    'travel_from' => $planning->start_address ?: 'kantoor',
                    'travel_to' => $planning->locations->first()?->name,
                    'travel_info' => null, // No travel for backlog tasks
                ];

                // Add call step after backlog tasks
                $locationSteps[] = [
                    'type' => 'call',
                    'title' => 'Bel kantoor',
                    'details' => 'Meld dat de backlog taken zijn voltooid en je onderweg bent naar de eerste locatie.',
                    'location_name' => 'Backlog taken',
                    'completed_tasks' => $tasksForBacklog,
                ];
            }
        }

        // Loop through the sorted locations on the route
        foreach ($planning->locations as $index => $location) {
            $locationTasks = $tasksByLocation[$location->id] ?? collect();

            // Partition into backlog vs standard for this location
            [$backlogLocationTasks, $standardLocationTasks] = $locationTasks->partition(fn ($pt) => ! is_null($pt->task_id));

            // Sort backlog tasks by priority
            $priorityOrder = [TaskPriority::HIGH->value => 1, TaskPriority::NORMAL->value => 2, TaskPriority::LOW->value => 3];
            $sortedBacklogLocation = $backlogLocationTasks->sortBy(fn ($pt) => $priorityOrder[$pt->task?->priority?->value] ?? 99);

            $sortedLocationTasks = $sortedBacklogLocation->concat($standardLocationTasks);

            $tasksForLocation = [];
            foreach ($sortedLocationTasks as $task) {
                $latestCompletion = $task->completions->sortByDesc('created_at')->first();
                $backlogPhotos = $task->task && $task->task->taskPhotos ? $task->task->taskPhotos->map(fn($photo) => $photo->url)->toArray() : [];

                // Check if task was skipped - only use skip data if task status is actually 'skipped'
                $skipCompletion = $task->completions->where('review_outcome', 'skipped')->sortByDesc('created_at')->first();
                $isSkipped = $task->status === TaskStatus::SKIPPED;

                $tasksForLocation[] = [
                    'title' => $task->title,
                    'details' => $task->description,
                    'task_id' => $task->id,
                    'status' => $task->status,
                    'completed_notes' => $latestCompletion->comment ?? null,
                    'photos' => $latestCompletion ? $latestCompletion->photos->pluck('url') : [],
                    'backlog_photos' => $backlogPhotos,
                    'skip_reason' => $isSkipped && $skipCompletion ? $skipCompletion->comment : null,
                    'skip_photos' => $isSkipped && $skipCompletion ? $skipCompletion->photos->pluck('url') : [],
                ];
            }

            // Calculate travel info to this location
            $travelInfo = null;
            if ($index === 0) {
                // First location - travel from start address
                $travelTime = $this->travelTimeService->calculateTravelTime(
                    $planning->start_address ?: 'kantoor',
                    $location
                );
                $travelInfo = [
                    'from' => $planning->start_address ?: 'kantoor',
                    'to' => $location->name,
                    'destination_address' => $location->name,
                    'duration_minutes' => $travelTime['duration_minutes'],
                    'duration_text' => $this->travelTimeService->formatDuration($travelTime['duration_minutes']),
                    'distance_km' => $travelTime['distance_km'],
            ];
            } else {
                // Travel from previous location
                $previousLocation = $planning->locations[$index - 1];
                $travelTime = $this->travelTimeService->calculateTravelTime($previousLocation, $location);
                $travelInfo = [
                    'from' => $previousLocation->name,
                    'to' => $location->name,
                    'destination_address' => $location->name,
                    'duration_minutes' => $travelTime['duration_minutes'],
                    'duration_text' => $this->travelTimeService->formatDuration($travelTime['duration_minutes']),
                    'distance_km' => $travelTime['distance_km'],
                ];
            }

            // Only add location if it has tasks
            if (!empty($tasksForLocation)) {
                // Add travel step if we have travel info and travel time > 0
                if ($travelInfo && $travelInfo['duration_minutes'] > 0) {
                    $locationSteps[] = [
                        'type' => 'travel',
                        'title' => "Reis naar {$location->name}",
                        'travel_id' => "travel_to_{$location->id}",
                        'destination_location_id' => $location->id,
                        'from' => $travelInfo['from'],
                        'to' => $travelInfo['to'],
                        'destination_address' => $travelInfo['destination_address'],
                        'duration_minutes' => $travelInfo['duration_minutes'],
                        'duration_text' => $travelInfo['duration_text'],
                        'distance_km' => $travelInfo['distance_km'],
                    ];
                }

                // Add location step
                $locationSteps[] = [
                    'type' => 'location',
                    'title' => $location->name,
                    'location_id' => $location->id,
                    'location_name' => $location->name,
                    'address' => $location->full_address,
                    'tasks' => $tasksForLocation,
                    'travel_info' => $travelInfo,
                    'outdoor_safe_code' => $location->outdoor_safe_code,
                    'indoor_safe_code' => $location->indoor_safe_code,
                    'outdoor_safe_content' => $location->outdoor_safe_content,
                    'indoor_safe_content' => $location->indoor_safe_content,
                    'intratone_number' => $location->intratone_number,
                    'intratone_multiple_numbers' => $location->intratone_multiple_numbers,
                    'gate_number' => $location->gate_number,
                ];

                // Add call step after each location
                $locationSteps[] = [
                    'type' => 'call',
                    'title' => 'Bel kantoor',
                    'details' => "Meld dat de taken op {$location->name} zijn voltooid.",
                    'location_name' => $location->name,
                    'completed_tasks' => $tasksForLocation,
                ];
            }
        }

        // Collect end-of-day actions from completed tasks
        $endDayActions = collect();
        foreach ($planning->planningTasks as $planningTask) {
            // Check if task is completed or in review (handle both enum and string values)
            $isCompletedOrReview = $planningTask->status === TaskStatus::COMPLETED ||
                                  $planningTask->status === 'completed' ||
                                  $planningTask->status === TaskStatus::REVIEW ||
                                  $planningTask->status === 'review';

            if ($isCompletedOrReview) {
                // Get end day actions from backlog task
                if ($planningTask->task && ($planningTask->task->end_day_action_title || $planningTask->task->end_day_action_description)) {
                    $endDayActions->push([
                        'id' => 'task_' . $planningTask->task->id,
                        'title' => $planningTask->task->end_day_action_title,
                        'description' => $planningTask->task->end_day_action_description,
                        'source' => $planningTask->task->title,
                        'location' => $planningTask->task->location->name ?? $planningTask->specificLocation->name ?? 'Onbekende locatie',
                    ]);
                }

                // Get end day actions from default task
                if ($planningTask->defaultTask && ($planningTask->defaultTask->end_day_action_title || $planningTask->defaultTask->end_day_action_description)) {
                    $endDayActions->push([
                        'id' => 'default_task_' . $planningTask->defaultTask->id . '_' . $planningTask->id,
                        'title' => $planningTask->defaultTask->end_day_action_title,
                        'description' => $planningTask->defaultTask->end_day_action_description,
                        'source' => $planningTask->defaultTask->title,
                        'location' => $planningTask->specificLocation->name ?? 'Onbekende locatie',
                    ]);
                }
            }
        }

        // Add end-of-day checklist as final step if there are items to return or actions to perform
        if ($uniqueBenodigdheden->isNotEmpty() || $endDayActions->isNotEmpty()) {
            // Create end checklist items if they don't exist yet for this planning
            $this->ensureEndChecklistItemsExist($planning, $uniqueBenodigdheden, $endDayActions);

            // Get the current checklist items with their status and photos
            $checklistItems = $planning->endChecklistItems()->with(['benodigdheid', 'reviewer'])->get();

            $locationSteps[] = [
                'type' => 'end_checklist',
                'title' => 'Eind checklist',
                'details' => 'Upload foto\'s als bewijs en dien de checklist in voor beoordeling',
                'checklist_items' => $checklistItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'type' => $item->type,
                        'title' => $item->title,
                        'description' => $item->description,
                        'photo_path' => $item->photo_path,
                        'photo_url' => $item->photo_path ? asset('storage/' . $item->photo_path) : null,
                        'status' => $item->status,
                        'admin_notes' => $item->admin_notes,
                        'reviewed_at' => $item->reviewed_at,
                        'reviewer_name' => $item->reviewer?->name,
                        'uploaded_by_name' => $item->uploader?->name,
                        'uploaded_at' => $item->uploaded_at,
                        'location_name' => $item->location?->name,
                    ];
                })->all(),
                'has_submitted' => $planning->hasSubmittedEndChecklist(),
                'is_approved' => $planning->hasApprovedEndChecklist(),
                'planning_id' => $planning->id,
            ];
        }

        // Calculate travel times between locations (same as in planning show)
        $travelTimes = null;
        if ($planning->locations->count() > 1) {
            $travelTimes = $this->travelTimeService->calculateTravelTimesForSequence(
                $planning->locations->all(),
                $planning->start_address
            );
        }

        // Calculate task times (same as in planning show)
        $totalTaskMinutes = $planning->planningTasks->sum(function ($planningTask) {
            if ($planningTask->task && isset($planningTask->task->estimated_time_minutes)) {
                return (int)$planningTask->task->estimated_time_minutes;
            } elseif ($planningTask->defaultTask && isset($planningTask->defaultTask->estimated_time_minutes)) {
                return (int)$planningTask->defaultTask->estimated_time_minutes;
            }
            return 0;
        });

        // Calculate time overview
        $timeOverview = [
            'task_minutes' => $totalTaskMinutes,
            'travel_minutes' => $travelTimes ? $travelTimes['total_duration_minutes'] : 0,
            'total_minutes' => $totalTaskMinutes + ($travelTimes ? $travelTimes['total_duration_minutes'] : 0),
        ];

        return view('my-planning.show', [
            'planning' => $planning,
            'locationSteps' => $locationSteps,
            'travelTimes' => $travelTimes,
            'timeOverview' => $timeOverview
        ]);
    }

    /**
     * Restart timer for a specific location in a planning.
     * This preserves the previous duration and starts counting again.
     */
    public function restartLocationTimer(Request $request, Planning $planning, $locationId)
    {
        $request->validate([
            'previous_duration' => 'required|integer|min:0',
        ]);

        // Determine location type and actual location ID
        $actualLocationId = null;
        $locationType = 'location';

        if ($locationId === 'backlog') {
            $actualLocationId = null;
            $locationType = 'backlog';
        } elseif (str_starts_with($locationId, 'travel_to_')) {
            // Travel timer - extract destination location ID
            $actualLocationId = str_replace('travel_to_', '', $locationId);
            $locationType = 'travel';
        } else {
            // Regular location
            $actualLocationId = $locationId;
            $locationType = 'location';
        }

        $timer = PlanningLocationTimer::where('planning_id', $planning->id)
            ->where('location_id', $actualLocationId)
            ->where('location_type', $locationType)
            ->first();

        if (!$timer) {
            return response()->json(['error' => 'Timer not found'], 404);
        }

        // Restart the timer - set new start time, clear end time, preserve previous duration
        $timer->update([
            'started_at' => now(),
            'ended_at' => null,
            'total_duration_seconds' => $request->input('previous_duration'), // Keep the accumulated time
        ]);

        return response()->json([
            'success' => true,
            'timer' => [
                'started_at' => $timer->started_at->toISOString(),
                'ended_at' => null,
                'total_duration' => $timer->total_duration_seconds,
            ],
        ]);
    }

    private function syncLocations(Planning $planning, array $locationIds, ?string $locationOrder): void
    {
        // Detach all current locations
        $planning->locations()->detach();

        // If locationOrder is provided, use it; otherwise, use the original order
        if ($locationOrder) {
            $orderedLocationIds = explode(',', $locationOrder);
            // Filter to only include IDs that are actually in $locationIds
            $orderedLocationIds = array_filter($orderedLocationIds, fn($id) => in_array($id, $locationIds));
            // Add any missing IDs from $locationIds
            foreach ($locationIds as $id) {
                if (!in_array($id, $orderedLocationIds)) {
                    $orderedLocationIds[] = $id;
                }
            }
        } else {
            $orderedLocationIds = $locationIds;
        }

        // Attach locations in the specified order
        foreach ($orderedLocationIds as $index => $locationId) {
            $planning->locations()->attach($locationId, ['sort_order' => $index + 1]);
        }
    }

    /**
     * Ensure that end checklist items exist for the given planning.
     * This will create items if they don't exist yet or update them if the requirements have changed.
     */
    private function ensureEndChecklistItemsExist(Planning $planning, $uniqueBenodigdheden, $endDayActions): void
    {
        // Get existing checklist items
        $existingItems = $planning->endChecklistItems()->get();

        // Create a list of expected items
        $expectedItems = collect();

        // Add material items (benodigdheden)
        foreach ($uniqueBenodigdheden as $benodigdheid) {
            $expectedItems->push([
                'type' => 'material',
                'benodigdheid_id' => isset($benodigdheid->is_location_specific) && $benodigdheid->is_location_specific ?
                    $benodigdheid->original_id : $benodigdheid->id,
                'location_id' => isset($benodigdheid->location_id) ? $benodigdheid->location_id : null,
                'title' => $benodigdheid->naam,
                'description' => "Terugbrengen: {$benodigdheid->naam}",
                'unique_key' => 'material_' . (isset($benodigdheid->is_location_specific) && $benodigdheid->is_location_specific ?
                    $benodigdheid->id : $benodigdheid->id), // Use composite ID for location-specific items
            ]);
        }

        // Add end action items
        foreach ($endDayActions as $endAction) {
            // Try to find the location ID based on the location name
            $locationId = null;
            if (isset($endAction['location'])) {
                $location = $planning->locations->firstWhere('name', $endAction['location']);
                $locationId = $location?->id;
            }

            $expectedItems->push([
                'type' => 'end_action',
                'benodigdheid_id' => null,
                'location_id' => $locationId,
                'title' => $endAction['title'],
                'description' => $endAction['description'],
                'unique_key' => 'end_action_' . $endAction['id'],
            ]);
        }

        // Create items that don't exist yet
        foreach ($expectedItems as $expectedItem) {
            $exists = $existingItems->contains(function ($item) use ($expectedItem) {
                if ($item->type !== $expectedItem['type']) {
                    return false;
                }

                if ($expectedItem['type'] === 'material') {
                    return $item->benodigdheid_id == $expectedItem['benodigdheid_id'] &&
                           $item->title === $expectedItem['title'];
                } else {
                    return $item->title === $expectedItem['title'] &&
                           $item->description === $expectedItem['description'];
                }
            });

            if (!$exists) {
                EndChecklistItem::create([
                    'planning_id' => $planning->id,
                    'location_id' => $expectedItem['location_id'] ?? null,
                    'type' => $expectedItem['type'],
                    'benodigdheid_id' => $expectedItem['benodigdheid_id'],
                    'title' => $expectedItem['title'],
                    'description' => $expectedItem['description'],
                ]);
            }
        }

        // Remove items that are no longer needed (only if they haven't been reviewed yet)
        $expectedKeys = $expectedItems->pluck('unique_key');
        foreach ($existingItems as $existingItem) {
            $currentKey = $existingItem->type . '_' .
                ($existingItem->type === 'material' ? $existingItem->benodigdheid_id :
                 ($existingItem->type === 'end_action' ? $existingItem->title : 'unknown'));

            if (!$expectedKeys->contains($currentKey) && $existingItem->status === 'pending' && !$existingItem->photo_path) {
                // Only delete items that haven't been started yet
                $existingItem->delete();
            }
        }
    }
}
