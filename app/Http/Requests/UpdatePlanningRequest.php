<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Location;
use App\Models\DefaultTask;
use App\Models\Task;

class UpdatePlanningRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Implement proper authorization later
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the planning being updated from the route
        $planning_id = $this->route('planning') ? $this->route('planning')->id : null;
        $selected_location_ids = $this->input('location_ids', []); // Haal de geselecteerde locatie IDs op

        return [
            'location_ids' => 'required|array|min:1',
            'location_ids.*' => ['integer', Rule::exists(Location::class, 'id')],
            'planned_date' => 'required|date',
            'notes' => 'nullable|string',
            'user_ids' => 'nullable|array',
            'user_ids.*' => ['integer', Rule::exists(\App\Models\User::class, 'id')],
            'selected_default_tasks' => 'nullable|array',
            'selected_default_tasks.*' => [
                'integer',
                Rule::exists(DefaultTask::class, 'id'),
                function ($attribute, $value, $fail) use ($selected_location_ids) {
                    $defaultTask = DefaultTask::with('locations')->find($value);
                    // Check of de default task gekoppeld is aan minstens één van de geselecteerde locaties
                    if (!$defaultTask || $defaultTask->locations->pluck('id')->intersect($selected_location_ids)->isEmpty()) {
                        $fail("De geselecteerde standaardtaak '{$defaultTask->title}' hoort niet bij de gekozen locatie(s).");
                    }
                }
            ],
            'selected_backlog_tasks' => 'nullable|array',
            'selected_backlog_tasks.*' => [
                'integer',
                Rule::exists(Task::class, 'id'),
                function ($attribute, $value, $fail) use ($planning_id, $selected_location_ids) {
                    $task = Task::find($value);
                    if (!$task) {
                        $fail("De geselecteerde backlog taak ({$value}) bestaat niet.");
                        return;
                    }
                    // Valideer dat de backlog taak bij één van de geselecteerde locaties hoort
                    if (!in_array($task->location_id, $selected_location_ids)) {
                        $fail("De geselecteerde backlog taak '{$task->title}' hoort niet bij de gekozen locatie(s).");
                        return;
                    }
                    // Valideer status
                    if (!in_array($task->status, ['open', 'in_progress'])) {
                        $fail("De geselecteerde backlog taak '{$task->title}' heeft niet de status open of in uitvoering. Status is: " . $task->status);
                        return;
                    }
                    // Check of de taak al aan een *andere* actieve planning is gekoppeld
                    $query = $task->planningTasks()->whereHas('planning', function($q) use ($planning_id) {
                        if ($planning_id) {
                            $q->where('id', '!=', $planning_id);
                        }
                        // Hier zou je nog kunnen filteren op actieve planningen, b.v. niet 'completed' status
                    });
                    if ($query->exists()) {
                        $fail("De backlog taak '{$task->title}' is al aan een andere planning toegewezen.");
                    }
                }
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'selected_default_tasks.*.exists' => 'Een geselecteerde standaardtaak is ongeldig.',
            'selected_backlog_tasks.*.exists' => 'Een geselecteerde backlog taak is ongeldig.',
        ];
    }
}
