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

        // Combined query for both completion photos and regular task photos
        $completionPhotos = DB::table('planning_task_completion_photos')
            ->select(
                'planning_task_completion_photos.id',
                'planning_task_completion_photos.file_path',
                'planning_task_completion_photos.room',
                'planning_task_completion_photos.created_at',
                DB::raw("'completion' as type"),
                'planning_tasks.id as planning_task_id',
                'planning_tasks.location_id as pt_location_id',
                'tasks.location_id as t_location_id'
            )
            ->join('planning_task_completions', 'planning_task_completion_photos.completion_id', '=', 'planning_task_completions.id')
            ->join('planning_tasks', 'planning_task_completions.planning_task_id', '=', 'planning_tasks.id')
            ->leftJoin('tasks', 'planning_tasks.task_id', '=', 'tasks.id');

        $taskPhotos = DB::table('planning_task_photos')
            ->select(
                'planning_task_photos.id',
                'planning_task_photos.path as file_path',
                'planning_task_photos.room',
                'planning_task_photos.created_at',
                DB::raw("'task' as type"),
                'planning_tasks.id as planning_task_id',
                'planning_tasks.location_id as pt_location_id',
                'tasks.location_id as t_location_id'
            )
            ->join('planning_tasks', 'planning_task_photos.planning_task_id', '=', 'planning_tasks.id')
            ->leftJoin('tasks', 'planning_tasks.task_id', '=', 'tasks.id');

        if ($locationId) {
            $completionPhotos->where(function($q) use ($locationId) {
                $q->where('planning_tasks.location_id', $locationId)
                  ->orWhere('tasks.location_id', $locationId);
            });
            $taskPhotos->where(function($q) use ($locationId) {
                $q->where('planning_tasks.location_id', $locationId)
                  ->orWhere('tasks.location_id', $locationId);
            });
        }

        if ($room) {
            $completionPhotos->where('planning_task_completion_photos.room', $room);
            $taskPhotos->where('planning_task_photos.room', $room);
        }

        $allPhotosQuery = $completionPhotos->unionAll($taskPhotos)
            ->orderBy('created_at', 'desc');

        $photos = $allPhotosQuery->paginate($perPage)->withQueryString();

        // Map results back to some form of usable objects or just use as is
        // For the view, we might need some relation data, but we can fetch them or use joins
        // Given we need location names and other stuff, maybe it's better to fetch by IDs after pagination
        // or just join location name too.

        $locations = Location::orderBy('name')->get();

        // Enhance photos with location name
        $photos->getCollection()->transform(function($photo) use ($locations) {
            $locId = $photo->pt_location_id ?: $photo->t_location_id;
            $photo->location_name = $locations->firstWhere('id', $locId)->name ?? 'Unknown Location';
            $photo->location_id = $locId;
            return $photo;
        });

        return view('media-library.index', compact('photos', 'locations', 'perPage', 'locationId', 'room'));
    }
}
