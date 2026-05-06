<?php

namespace App\Http\Controllers;

// Completion Photo model.
use App\Models\PlanningTaskCompletionPhoto;
use App\Models\PlanningTaskPhoto;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MediaLibraryController extends Controller
{
    /**
     * Display a listing of the media resources.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 24);
        $locationId = $request->input('location_id');
        $room = $request->input('room');

        // 1. Planning Task Completion Photos
        $completionPhotos = DB::table('planning_task_completion_photos')
            ->select(
                'planning_task_completion_photos.id',
                'planning_task_completion_photos.file_path',
                'planning_task_completion_photos.room',
                'planning_task_completion_photos.created_at',
                DB::raw("'planning_completion' as type"),
                DB::raw('COALESCE(planning_tasks.location_id, tasks.location_id) as location_id'),
                'planning_tasks.id as planning_task_id'
            )
            ->join('planning_task_completions', 'planning_task_completion_photos.completion_id', '=', 'planning_task_completions.id')
            ->join('planning_tasks', 'planning_task_completions.planning_task_id', '=', 'planning_tasks.id')
            ->leftJoin('tasks', 'planning_tasks.task_id', '=', 'tasks.id');

        // 2. Regular Planning Task Photos
        $taskPhotos = DB::table('planning_task_photos')
            ->select(
                'planning_task_photos.id',
                'planning_task_photos.path as file_path',
                'planning_task_photos.room',
                'planning_task_photos.created_at',
                DB::raw("'planning_task' as type"),
                DB::raw('COALESCE(planning_tasks.location_id, tasks.location_id) as location_id'),
                'planning_tasks.id as planning_task_id'
            )
            ->join('planning_tasks', 'planning_task_photos.planning_task_id', '=', 'planning_tasks.id')
            ->leftJoin('tasks', 'planning_tasks.task_id', '=', 'tasks.id');

        // 3. Planning Comment Photos
        $commentPhotos = DB::table('planning_comment_photos')
            ->select(
                'id',
                'file_path',
                'room',
                'created_at',
                DB::raw("'comment' as type"),
                'location_id',
                DB::raw('NULL as planning_task_id')
            );

        // 4. End Checklist Item Photos
        $checklistPhotos = DB::table('end_checklist_item_photos')
            ->select(
                'end_checklist_item_photos.id',
                'end_checklist_item_photos.file_path',
                DB::raw('NULL as room'),
                'end_checklist_item_photos.created_at',
                DB::raw("'checklist' as type"),
                'end_checklist_items.location_id',
                DB::raw('NULL as planning_task_id')
            )
            ->join('end_checklist_items', 'end_checklist_item_photos.end_checklist_item_id', '=', 'end_checklist_items.id');

        // 5. General Task Photos (not necessarily linked to planning)
        $generalTaskPhotos = DB::table('task_photos')
            ->select(
                'task_photos.id',
                'task_photos.file_path',
                DB::raw('NULL as room'),
                'task_photos.created_at',
                DB::raw("'task' as type"),
                'tasks.location_id',
                DB::raw('NULL as planning_task_id')
            )
            ->join('tasks', 'task_photos.task_id', '=', 'tasks.id');

        // 6. General Task Completion Photos
        $generalTaskCompletionPhotos = DB::table('task_completion_photos')
            ->select(
                'task_completion_photos.id',
                'task_completion_photos.file_path',
                'task_completion_photos.room',
                'task_completion_photos.created_at',
                DB::raw("'task_completion' as type"),
                'tasks.location_id',
                DB::raw('NULL as planning_task_id')
            )
            ->join('task_completions', 'task_completion_photos.task_completion_id', '=', 'task_completions.id')
            ->join('tasks', 'task_completions.task_id', '=', 'tasks.id');

        if ($locationId) {
            $completionPhotos->where(function($q) use ($locationId) {
                $q->where('planning_tasks.location_id', $locationId)
                  ->orWhere('tasks.location_id', $locationId);
            });
            $taskPhotos->where(function($q) use ($locationId) {
                $q->where('planning_tasks.location_id', $locationId)
                  ->orWhere('tasks.location_id', $locationId);
            });
            $commentPhotos->where('location_id', $locationId);
            $checklistPhotos->where('end_checklist_items.location_id', $locationId);
            $generalTaskPhotos->where('tasks.location_id', $locationId);
            $generalTaskCompletionPhotos->where('tasks.location_id', $locationId);
        }

        if ($room) {
            $completionPhotos->where('planning_task_completion_photos.room', $room);
            $taskPhotos->where('planning_task_photos.room', $room);
            $commentPhotos->where('room', $room);
            // checklist and general task photos don't always have rooms, but let's filter if they do
            $generalTaskCompletionPhotos->where('task_completion_photos.room', $room);
            // for those without room, they won't match if $room is set
            $checklistPhotos->whereRaw('1=0');
            $generalTaskPhotos->whereRaw('1=0');
        }

        $allPhotosQuery = $completionPhotos
            ->unionAll($taskPhotos)
            ->unionAll($commentPhotos)
            ->unionAll($checklistPhotos)
            ->unionAll($generalTaskPhotos)
            ->unionAll($generalTaskCompletionPhotos)
            ->orderBy('created_at', 'desc');

        $photos = $allPhotosQuery->paginate($perPage)->withQueryString();

        $locations = Location::orderBy('name')->get();

        // Enhance photos with location name
        $photos->getCollection()->transform(function($photo) use ($locations) {
            $photo->location_name = $locations->firstWhere('id', $photo->location_id)->name ?? 'Onbekende locatie';
            return $photo;
        });

        return view('media-library.index', compact('photos', 'locations', 'perPage', 'locationId', 'room'));
    }
}
