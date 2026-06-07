<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\EndChecklistItem;
use App\Models\Location;
use App\Models\Planning;
use App\Models\PlanningComment;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Support\Collection;

class MyPlanningShowDataService
{
    public function __construct(
        private TravelTimeService $travelTimeService
    ) {}

    public function resolvePlanningForUser(?Planning $planning, User $user): ?Planning
    {
        if (! $planning) {
            $today = now()->startOfDay();

            return Planning::where('planned_date', $today)
                ->whereHas('users', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->with(['locations', 'vehicle', 'planningTasks.specificLocation', 'planningTasks.task.location', 'planningTasks.task.taskPhotos', 'planningTasks.task.requirements', 'planningTasks.defaultTask.requirements', 'planningTasks.completions.photos', 'comments.photos'])
                ->first();
        }

        $planning->load(['locations', 'vehicle', 'planningTasks.specificLocation', 'planningTasks.task.location', 'planningTasks.task.taskPhotos', 'planningTasks.task.requirements', 'planningTasks.defaultTask.requirements', 'planningTasks.completions.photos', 'comments.photos']);

        if (! $user->isAdmin() && ! $planning->users->contains($user)) {
            abort(403, 'Je hebt geen toegang tot deze planning.');
        }

        return $planning;
    }

    /**
     * @return array{
     *   locationSteps: array<int, array<string, mixed>>,
     *   travelTimes: array<string, mixed>|null,
     *   timeOverview: array{task_minutes: int, travel_minutes: int|float, total_minutes: int|float},
     *   allLocations: Collection<int, Location>
     * }
     */
    public function buildShowData(Planning $planning): array
    {
        $locationSteps = [];

        // Collect all requirements from tasks AND automatically required ones for locations
        $allRequirements = collect();
        $locationSpecificRequirements = collect(); // Track location-specific variants
        // Track for which locations each requirement applies (by requirement id or composite id for location-specific)
        $requirementLocations = [];

        // Get requirements from tasks (these don't get location replacement)
        foreach ($planning->planningTasks as $planningTask) {
            // Determine the location context for this planning task
            $taskLocationName = null;
            if ($planningTask->task?->location != null) {
                $taskLocationName = $planningTask->task->location->name;
            } elseif ($planningTask->specificLocation) {
                $taskLocationName = $planningTask->specificLocation->name;
            }

            // Get requirements from backlog tasks
            if ($planningTask->task?->requirements != null) {
                $allRequirements = $allRequirements->merge($planningTask->task->requirements);
                if ($taskLocationName) {
                    foreach ($planningTask->task->requirements as $req) {
                        $requirementLocations[$req->id] = array_values(array_unique(array_merge($requirementLocations[$req->id] ?? [], [$taskLocationName])));
                    }
                }
            }
            // Get requirements from default tasks
            if ($planningTask->defaultTask?->requirements != null) {
                $allRequirements = $allRequirements->merge($planningTask->defaultTask->requirements);
                if ($taskLocationName) {
                    foreach ($planningTask->defaultTask->requirements as $req) {
                        $requirementLocations[$req->id] = array_values(array_unique(array_merge($requirementLocations[$req->id] ?? [], [$taskLocationName])));
                    }
                }
            }
        }

        // Get automatically required requirements for selected locations
        if (! empty($planning->locations)) {
            foreach ($planning->locations as $location) {
                $automaticRequirements = Requirement::whereHas('requiredForLocations', function ($query) use ($location) {
                    $query->where('location_id', $location->id);
                })->get();

                foreach ($automaticRequirements as $requirement) {
                    if (str_contains($requirement->name, '[locatie]')) {
                        // Create location-specific variant
                        $compositeId = $requirement->id.'_'.$location->id;
                        $locationSpecificRequirements->push((object) [
                            'id' => $compositeId, // Unique ID for this variant
                            'original_id' => $requirement->id,
                            'naam' => str_replace('[locatie]', $location->name, $requirement->name),
                            'beschrijving' => $requirement->description,
                            'location_name' => $location->name,
                            'is_location_specific' => true,
                        ]);
                        // Track the location for this variant
                        $requirementLocations[$compositeId] = array_values(array_unique(array_merge($requirementLocations[$compositeId] ?? [], [$location->name])));
                    } else {
                        // Add as regular requirement (no location replacement needed)
                        $allRequirements->push($requirement);
                        // Track that this requirement applies to this planning location
                        $requirementLocations[$requirement->id] = array_values(array_unique(array_merge($requirementLocations[$requirement->id] ?? [], [$location->name])));
                    }
                }
            }
        }

        // Merge regular requirements with location-specific variants
        $allRequirementsWithVariants = $allRequirements->concat($locationSpecificRequirements);

        // Remove duplicates (only for regular requirements, keep all location-specific variants)
        $uniqueRequirements = $allRequirementsWithVariants->unique(function ($item) {
            if (isset($item->is_location_specific) && $item->is_location_specific) {
                return $item->id; // Use composite ID for location-specific items
            }

            return $item->id; // Use original ID for regular items
        })->sortBy(function ($item) {
            return isset($item->is_location_specific) && $item->is_location_specific
                ? ($item->naam ?? '')
                : ($item->name ?? '');
        });

        // Add summary as first step
        $locationSteps[] = [
            'type' => 'summary',
            'title' => 'Samenvatting van je dag',
            'details' => 'Bekijk je planning overzicht voordat je begint',
        ];

        // Add requirements checklist as second step (only if there are requirements)
        if ($uniqueRequirements->isNotEmpty()) {
            $locationSteps[] = [
                'type' => 'requirements',
                'title' => 'Benodigdheden checklist',
                'details' => 'Controleer of je alle benodigde materialen hebt voordat je begint',
                'requirements' => $uniqueRequirements->map(function ($requirement) use ($requirementLocations) {
                    $id = $requirement->id;

                    return [
                        'id' => $id,
                        'naam' => $requirement->naam ?? $requirement->name,
                        'beschrijving' => $requirement->beschrijving ?? $requirement->description,
                        'locaties' => $requirementLocations[$id] ?? [],
                    ];
                })->values()->all(),
            ];
        }

        // Group tasks by their effective location_id (from planning_task or fallback to parent task)
        $tasksByLocation = $planning->planningTasks->groupBy(function ($planningTask): string {
            return (string) ($planningTask->location_id ?? $planningTask->task?->location_id);
        });

        // Add tasks with no location (true backlog and vehicle tasks) first as a "location"
        if (isset($tasksByLocation[''])) {
            $noLocationTasks = $tasksByLocation[''];

            // Partition vehicle tasks first
            $vehicleTasks = $noLocationTasks->filter(fn ($pt) => (bool) $pt->is_vehicle_task === true);
            $nonVehicleTasks = $noLocationTasks->reject(fn ($pt) => (bool) $pt->is_vehicle_task === true);

            // Within non-vehicle tasks, partition into backlog vs standard
            [$backlogTasks, $standardTasks] = $nonVehicleTasks->partition(fn ($pt) => ! is_null($pt->task_id));

            // Sort backlog tasks by priority
            $priorityOrder = [TaskPriority::HIGH->value => 1, TaskPriority::NORMAL->value => 2, TaskPriority::LOW->value => 3];
            $sortedBacklog = $backlogTasks->sortBy(fn ($pt) => $priorityOrder[$pt->task?->priority?->value] ?? 99);

            // Final order: vehicle tasks first, then backlog (by priority), then standard
            $sortedTasks = $vehicleTasks->concat($sortedBacklog)->concat($standardTasks);

            $tasksForBacklog = [];
            foreach ($sortedTasks as $task) {
                $latestCompletion = $task->completions->sortByDesc('created_at')->first();

                // Check if task was skipped - only use skip data if task status is actually 'skipped'
                $skipCompletion = $task->completions->where('review_outcome', 'skipped')->sortByDesc('created_at')->first();
                $isSkipped = $task->status === TaskStatus::SKIPPED;

                $tasksForBacklog[] = [
                    'title' => $task->title,
                    'details' => $task->description,
                    'task_id' => $task->id,
                    'status' => $task->status,
                    'completed_notes' => $latestCompletion ? $latestCompletion->comment : ($task->completed_notes ?? null),
                    'photos' => $latestCompletion ? $latestCompletion->photos->map(fn ($p) => ['id' => $p->id, 'url' => $p->url]) : [],
                    'backlog_photos' => $task->task ? $task->task->taskPhotos->map(fn ($p) => ['id' => $p->id, 'url' => $p->url]) : [],
                    'skip_reason' => $isSkipped && $skipCompletion ? $skipCompletion->comment : null,
                    'skip_photos' => $isSkipped && $skipCompletion ? $skipCompletion->photos->map(fn ($p) => ['id' => $p->id, 'url' => $p->url]) : [],
                    'is_extra' => ! $task->task_id && ! $task->default_task_id && ! $task->vehicle_task_id,
                    'is_photo_required' => (bool) ($task->task->is_photo_required ?? $task->defaultTask->is_photo_required ?? false),
                    'room' => $task->task->room ?? $task->room_identifier,
                    'room_identifier' => $task->room_identifier,
                    'is_inactive_room_task' => ! is_null($task->room_identifier),
                    'photo_process_step' => $task->task?->photo_process_step,
                    'photo_process_at' => $task->task?->photo_process_at?->format('d-m-Y H:i'),
                    'underlying_task_id' => $task->task_id,
                    'external_id' => null,
                ];
            }

            // Get comments for this location (backlog)
            $commentsForBacklog = $planning->comments
                ->whereNull('location_id')
                ->map(fn (PlanningComment $comment) => $this->mapPlanningCommentForView($comment))
                ->values()
                ->all();

            if (! empty($tasksForBacklog) || ! empty($commentsForBacklog)) {
                $locationSteps[] = [
                    'type' => 'location',
                    'title' => 'Backlog taken',
                    'location_id' => null,
                    'location_name' => 'Backlog',
                    'address' => null,
                    'tasks' => $tasksForBacklog,
                    'comments' => $commentsForBacklog,
                    'travel_from' => $planning->start_address ?: 'kantoor',
                    'travel_to' => $planning->locations->first()?->name,
                    'travel_info' => null, // No travel for backlog tasks
                ];

                // Add call step after backlog tasks
                $locationSteps[] = [
                    'type' => 'call',
                    'title' => 'Bel Jaap',
                    'details' => 'Meld dat de backlog taken zijn voltooid en je onderweg bent naar de eerste locatie.',
                    'location_name' => 'Backlog taken',
                    'completed_tasks' => $tasksForBacklog,
                ];
            }
        }

        // Loop through the sorted locations on the route
        foreach ($planning->locations as $index => $location) {
            $locationTasks = $tasksByLocation[$location->id] ?? collect();

            // Split into regular tasks and inactive room tasks
            [$inactiveRoomTasks, $otherLocationTasks] = $locationTasks->partition(fn ($pt) => ! is_null($pt->room_identifier));

            // Partition into backlog vs standard for this location
            [$backlogLocationTasks, $standardLocationTasks] = $otherLocationTasks->partition(fn ($pt) => ! is_null($pt->task_id));

            // Sort backlog tasks by priority
            $priorityOrder = [TaskPriority::HIGH->value => 1, TaskPriority::NORMAL->value => 2, TaskPriority::LOW->value => 3];
            $sortedBacklogLocation = $backlogLocationTasks->sortBy(fn ($pt) => $priorityOrder[$pt->task?->priority?->value] ?? 99);

            $sortedLocationTasks = $sortedBacklogLocation->concat($standardLocationTasks);

            $tasksForLocation = [];
            foreach ($sortedLocationTasks as $task) {
                $latestCompletion = $task->completions->sortByDesc('created_at')->first();

                // Check if task was skipped - only use skip data if task status is actually 'skipped'
                $skipCompletion = $task->completions->where('review_outcome', 'skipped')->sortByDesc('created_at')->first();
                $isSkipped = $task->status === TaskStatus::SKIPPED;

                $tasksForLocation[] = [
                    'title' => $task->title,
                    'details' => $task->description,
                    'task_id' => $task->id,
                    'status' => $task->status,
                    'completed_notes' => $latestCompletion ? $latestCompletion->comment : ($task->completed_notes ?? null),
                    'photos' => $latestCompletion ? $latestCompletion->photos->map(fn ($p) => ['id' => $p->id, 'url' => $p->url]) : [],
                    'backlog_photos' => $task->task ? $task->task->taskPhotos->map(fn ($p) => ['id' => $p->id, 'url' => $p->url]) : [],
                    'skip_reason' => $isSkipped && $skipCompletion ? $skipCompletion->comment : null,
                    'skip_photos' => $isSkipped && $skipCompletion ? $skipCompletion->photos->map(fn ($p) => ['id' => $p->id, 'url' => $p->url]) : [],
                    'is_extra' => ! $task->task_id && ! $task->default_task_id && ! $task->vehicle_task_id,
                    'is_photo_required' => (bool) ($task->task->is_photo_required ?? $task->defaultTask->is_photo_required ?? false),
                    'room' => $task->task->room ?? $task->room_identifier,
                    'room_identifier' => $task->room_identifier,
                    'is_inactive_room_task' => ! is_null($task->room_identifier),
                    'photo_process_step' => $task->task?->photo_process_step,
                    'photo_process_at' => $task->task?->photo_process_at?->format('d-m-Y H:i'),
                    'underlying_task_id' => $task->task_id,
                    'external_id' => $location->external_id,
                    'sync_external_id' => $location->sync_external_id,
                ];
            }

            $inactiveRoomsForLocation = [];
            foreach ($inactiveRoomTasks as $task) {
                $latestCompletion = $task->completions->sortByDesc('created_at')->first();
                $inactiveRoomsForLocation[] = [
                    'title' => $task->title,
                    'details' => $task->description,
                    'description' => $task->description,
                    'task_id' => $task->id,
                    'status' => $task->status,
                    'completed_notes' => $latestCompletion ? $latestCompletion->comment : ($task->completed_notes ?? null),
                    'photos' => $latestCompletion ? $latestCompletion->photos->map(fn ($p) => ['id' => $p->id, 'url' => $p->url]) : [],
                    'room' => $task->room_identifier,
                    'room_identifier' => $task->room_identifier,
                    'room_group' => $task->room_group,
                    'is_inactive_room_task' => true,
                ];
            }

            // Get comments for this location
            $commentsForLocation = $planning->comments
                ->where('location_id', $location->id)
                ->map(fn (PlanningComment $comment) => $this->mapPlanningCommentForView($comment))
                ->values()
                ->all();

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

            // Only add location if it has tasks or comments or inactive room tasks or the check_inactive_spaces flag is set
            if (! empty($tasksForLocation) || ! empty($commentsForLocation) || ! empty($inactiveRoomsForLocation) || (bool) $location->pivot->check_inactive_spaces) {
                // Add travel step if we have travel info and travel time > 0
                if ($travelInfo != null && $travelInfo['duration_minutes'] > 0) {
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
                    'inactive_room_tasks' => $inactiveRoomsForLocation,
                    'check_inactive_spaces' => (bool) $location->pivot->check_inactive_spaces,
                    'comments' => $commentsForLocation,
                    'travel_info' => $travelInfo,
                ];

                // Add call step after each location
                $locationSteps[] = [
                    'type' => 'call',
                    'title' => 'Bel Jaap',
                    'details' => "Meld dat de taken op {$location->name} zijn voltooid.",
                    'location_name' => $location->name,
                    'completed_tasks' => $tasksForLocation,
                ];
            }
        }

        // After last location, add return travel step back to start address if applicable
        if ($planning->locations->count() > 0) {
            $lastLocation = $planning->locations->last();
            $returnTo = $planning->start_address ?: 'kantoor';
            $returnTravel = $this->travelTimeService->calculateTravelTime($lastLocation, $returnTo);
            if ($returnTravel['duration_minutes'] > 0) {
                $locationSteps[] = [
                    'type' => 'travel',
                    'title' => 'Reis terug naar start',
                    'travel_id' => 'travel_back',
                    'destination_location_id' => null,
                    'from' => $lastLocation->name,
                    'to' => $returnTo,
                    'destination_address' => $returnTo,
                    'duration_minutes' => $returnTravel['duration_minutes'],
                    'duration_text' => $this->travelTimeService->formatDuration($returnTravel['duration_minutes']),
                    'distance_km' => $returnTravel['distance_km'],
                ];
            }
        }

        // Collect end-of-day actions from completed tasks
        $endDayActions = collect();
        foreach ($planning->planningTasks as $planningTask) {
            // Check if task is completed or in review (handle both enum and string values)
            $isCompletedOrReview = $planningTask->status === TaskStatus::COMPLETED || $planningTask->status === TaskStatus::REVIEW;

            if ($isCompletedOrReview) {
                // Get end day actions from backlog task
                if ($planningTask->task && ($planningTask->task->end_day_action_title || $planningTask->task->end_day_action_description)) {
                    $endDayActions->push([
                        'id' => 'task_'.$planningTask->task->id,
                        'title' => $planningTask->task->end_day_action_title,
                        'description' => $planningTask->task->end_day_action_description,
                        'source' => $planningTask->task->title,
                        'location' => $planningTask->task->location->name ?? $planningTask->specificLocation->name ?? 'Onbekende locatie',
                    ]);
                }

                // Get end day actions from default task
                if ($planningTask->defaultTask && ($planningTask->defaultTask->end_day_action_title || $planningTask->defaultTask->end_day_action_description)) {
                    $endDayActions->push([
                        'id' => 'default_task_'.$planningTask->defaultTask->id.'_'.$planningTask->id,
                        'title' => $planningTask->defaultTask->end_day_action_title,
                        'description' => $planningTask->defaultTask->end_day_action_description,
                        'source' => $planningTask->defaultTask->title,
                        'location' => $planningTask->specificLocation->name ?? 'Onbekende locatie',
                    ]);
                }
            }
        }

        // Add end-of-day checklist as final step (followed by optional vehicle tasks step) if there are items to return or actions to perform
        if ($uniqueRequirements->isNotEmpty() || $endDayActions->isNotEmpty()) {
            // Create end checklist items if they don't exist yet for this planning
            $this->ensureEndChecklistItemsExist($planning, $uniqueRequirements, $endDayActions);

            // Get the current checklist items with their status and photos
            $checklistItems = $planning->endChecklistItems()->with(['requirement', 'reviewer'])->get();

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
                        // Needed on the client to rebuild payload when adding vehicle tasks
                        'requirement_id' => $item->requirement?->id,
                        'photo_path' => $item->photo_path,
                        'photo_url' => $item->photo_path ? asset('storage/'.$item->photo_path) : null,
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

            // If a vehicle is linked to this planning, add a separate step for creating vehicle tasks for the next day
            if ($planning->vehicle_id) {
                $locationSteps[] = [
                    'type' => 'vehicle_tasks',
                    'title' => 'Voertuig taken (volgende dag)',
                    'details' => 'Voeg optioneel voertuig taken toe die morgen als eerste verschijnen bij hetzelfde voertuig.',
                    'planning_id' => $planning->id,
                    'vehicle_name' => $planning->vehicle?->name,
                ];
            }
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
                return (int) $planningTask->task->estimated_time_minutes;
            } elseif ($planningTask->defaultTask && isset($planningTask->defaultTask->estimated_time_minutes)) {
                return (int) $planningTask->defaultTask->estimated_time_minutes;
            }

            return 0;
        });

        // Calculate time overview
        $timeOverview = [
            'task_minutes' => $totalTaskMinutes,
            'travel_minutes' => $travelTimes ? $travelTimes['total_duration_minutes'] : 0,
            'total_minutes' => $totalTaskMinutes + ($travelTimes ? $travelTimes['total_duration_minutes'] : 0),
        ];

        $allLocations = Location::orderBy('name')->get(['id', 'name']);

        return [
            'locationSteps' => $locationSteps,
            'travelTimes' => $travelTimes,
            'timeOverview' => $timeOverview,
            'allLocations' => $allLocations,
        ];
    }

    /**
     * @param  iterable<int, object>  $uniqueRequirements  List of requirement-like objects (may be Requirement models or location-specific wrappers)
     * @param  iterable<int, array{ id:int|string, title:string, description:string, location?:string }>  $endDayActions
     */
    private function ensureEndChecklistItemsExist(Planning $planning, iterable $uniqueRequirements, iterable $endDayActions): void
    {
        // Get existing checklist items
        $existingItems = $planning->endChecklistItems()->get();

        // Create a list of expected items
        $expectedItems = collect();

        // Add material items (requirements)
        foreach ($uniqueRequirements as $requirement) {
            $expectedItems->push([
                'type' => 'material',
                'requirement_id' => isset($requirement->is_location_specific) && $requirement->is_location_specific ?
                    $requirement->original_id : $requirement->id,
                'location_id' => isset($requirement->location_id) ? $requirement->location_id : null,
                'title' => ($requirement->naam ?? $requirement->name),
                'description' => 'Terugbrengen: '.($requirement->naam ?? $requirement->name),
                'unique_key' => 'material_'.(isset($requirement->is_location_specific) && $requirement->is_location_specific ?
                    $requirement->id : $requirement->id), // Use composite ID for location-specific items
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
                'requirement_id' => null,
                'location_id' => $locationId,
                'title' => $endAction['title'],
                'description' => $endAction['description'],
                'unique_key' => 'end_action_'.$endAction['id'],
            ]);
        }

        // Create items that don't exist yet
        foreach ($expectedItems as $expectedItem) {
            $exists = $existingItems->contains(function ($item) use ($expectedItem) {
                if ($item->type !== $expectedItem['type']) {
                    return false;
                }

                if ($expectedItem['type'] === 'material') {
                    return $item->requirement_id == $expectedItem['requirement_id'] &&
                           $item->title === $expectedItem['title'];
                } else {
                    return $item->title === $expectedItem['title'] &&
                           $item->description === $expectedItem['description'];
                }
            });

            if (! $exists) {
                EndChecklistItem::create([
                    'planning_id' => $planning->id,
                    'location_id' => $expectedItem['location_id'] ?? null,
                    'type' => $expectedItem['type'],
                    'requirement_id' => $expectedItem['requirement_id'] ?? null,
                    'title' => $expectedItem['title'],
                    'description' => $expectedItem['description'],
                ]);
            }
        }

        // Remove items that are no longer needed (only if they haven't been reviewed yet)
        $expectedKeys = $expectedItems->pluck('unique_key');
        foreach ($existingItems as $existingItem) {
            $map = [
                'material' => (string) ($existingItem->requirement_id),
                'end_action' => (string) ($existingItem->title),
            ];
            // If an unexpected type appears, skip deletion logic for safety
            if (! array_key_exists($existingItem->type, $map)) {
                continue;
            }
            $keyPart = (string) $map[$existingItem->type];
            $currentKey = $existingItem->type.'_'.$keyPart;

            if (! $expectedKeys->contains($currentKey) && $existingItem->isOpen() && ! $existingItem->photo_path) {
                // Only delete items that haven't been started yet (still 'open' and no photo)
                $existingItem->delete();
            }
        }
    }

    /**
     * @return array<int, array{id: int, url: string}>
     */
    private function mapPlanningCommentPhotos(PlanningComment $comment): array
    {
        $photos = [];

        foreach ($comment->photos as $photo) {
            $photos[] = [
                'id' => $photo->id,
                'url' => $photo->url,
            ];
        }

        return $photos;
    }

    /**
     * @return array{id: int, comment: string|null, photos: array<int, array{id: int, url: string}>, created_at: string}
     */
    private function mapPlanningCommentForView(PlanningComment $comment): array
    {
        return [
            'id' => $comment->id,
            'comment' => $comment->comment,
            'photos' => $this->mapPlanningCommentPhotos($comment),
            'created_at' => $comment->created_at->format('H:i'),
        ];
    }
}
