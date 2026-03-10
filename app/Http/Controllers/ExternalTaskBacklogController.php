<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreExternalTaskWebRequest;
use App\Http\Requests\UpdateExternalTaskWebRequest;
use App\Models\ExternalTask;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExternalTaskBacklogController extends Controller
{
    public function index(Request $request): View
    {
        $query = ExternalTask::query();

        $statusParam = $request->input('status');
        $validStatuses = array_column(TaskStatus::cases(), 'value');
        if (in_array($statusParam, $validStatuses, true)) {
            $query->where('status', $statusParam);
        }

        $searchTerm = $request->input('search_term');
        if (! empty($searchTerm)) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        $query->orderByRaw('external_deadline_at IS NULL ASC, external_deadline_at ASC')
            ->orderBy('created_at', 'desc');

        // Eager load relationships before pagination (paginator does not support load())
        $query->with(['location']);

        $perPage = $this->resolvePerPage($request, $query, 30);
        $tasks = $query->paginate($perPage)->withQueryString();

        return view('external-tasks.index', [
            'tasks' => $tasks,
            'searchTerm' => $searchTerm,
            'statusFilter' => $statusParam,
            'statusOptions' => TaskStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $locations = Location::orderBy('name')->get();
        $priorities = TaskPriority::cases();

        return view('external-tasks.create', compact('locations', 'priorities'));
    }

    public function store(StoreExternalTaskWebRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();
        $validatedData['status'] = TaskStatus::IN_REVIEW;

        $task = ExternalTask::create($validatedData);

        return redirect()->route('external-backlog.index')->with('success', "Externe taak \"{$task->title}\" succesvol aangemaakt.");
    }

    public function show(ExternalTask $externalTask): View
    {
        $externalTask->load(['location', 'comments.user']);

        return view('external-tasks.show', [
            'task' => $externalTask,
            'statusOptions' => TaskStatus::cases(),
        ]);
    }

    public function storeComment(Request $request, ExternalTask $externalTask): RedirectResponse
    {
        $validated = $request->validate([
            'comment' => 'required|string',
        ]);

        $externalTask->comments()->create([
            'user_id' => auth()->id(),
            'comment' => $validated['comment'],
        ]);

        return redirect()->route('external-backlog.show', $externalTask)->with('success', 'Opmerking geplaatst.');
    }

    public function edit(ExternalTask $externalTask): View
    {
        $locations = Location::orderBy('name')->get();
        $priorities = TaskPriority::cases();
        $statuses = TaskStatus::cases();

        return view('external-tasks.edit', [
            'task' => $externalTask,
            'locations' => $locations,
            'priorities' => $priorities,
            'statuses' => $statuses,
        ]);
    }

    public function update(UpdateExternalTaskWebRequest $request, ExternalTask $externalTask): RedirectResponse
    {
        $externalTask->update($request->validated());

        return redirect()->route('external-backlog.show', $externalTask)->with('success', 'Externe taak succesvol bijgewerkt.');
    }

    public function destroy(ExternalTask $externalTask): RedirectResponse
    {
        $externalTask->delete();

        return redirect()->route('external-backlog.index')->with('success', 'Externe taak succesvol verwijderd.');
    }

    public function updateStatus(Request $request, ExternalTask $externalTask): RedirectResponse
    {
        $validStatuses = array_column(TaskStatus::cases(), 'value');
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', $validStatuses)],
        ]);

        $externalTask->update(['status' => $validated['status']]);

        return redirect()->route('external-backlog.show', $externalTask)->with('success', 'Status bijgewerkt.');
    }


}
