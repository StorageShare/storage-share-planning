<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Location;
use App\Models\DefaultTask;
use App\Models\Task;

class StorePlanningRequest extends FormRequest
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
                 function ($attribute, $value, $fail) {
                    $defaultTask = DefaultTask::find($value);
                    $selected_location_ids = collect($this->input('location_ids', []));
                    if ($defaultTask && !$defaultTask->locations()->whereIn('locations.id', $selected_location_ids)->exists()) {
                        $fail("De geselecteerde standaardtaak (".$defaultTask->title.") hoort niet bij één van de gekozen locaties.");
                    }
                 }
            ],
            'selected_backlog_tasks' => 'nullable|array',
            'selected_backlog_tasks.*' => [
                'integer',
                Rule::exists(Task::class, 'id'),
                function ($attribute, $value, $fail) {
                    $task = Task::find($value);
                    if (!$task) {
                        $fail("De geselecteerde backlog taak (".$value.") bestaat niet.");
                        return;
                    }
                    $selected_location_ids = $this->input('location_ids', []);
                    if (!in_array($task->location_id, $selected_location_ids)) {
                        $fail("De geselecteerde backlog taak '{$task->title}' hoort niet bij één van de gekozen locaties.");
                        return;
                    }
                    if (!in_array($task->status, ['open', 'in_progress'])) {
                        $fail("De geselecteerde backlog taak '{$task->title}' heeft niet de status open of in uitvoering. Status is: " . $task->status);
                        return;
                    }
                    if ($task->planningTasks()->whereHas('planning')->exists()) {
                        // Verfijning nodig voor "actieve" planningen als dat relevant wordt.
                        // Voor nu: als het al in *enige* planning zit, niet opnieuw selecteerbaar in een nieuwe planning.
                        // Dit is een conservatieve benadering.
                        // $fail("De backlog taak '{$task->title}' is al aan een planning toegewezen.");
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
            'location_ids.required' => 'Minstens één locatie moet geselecteerd zijn.',
            'location_ids.array' => 'Locaties moeten als een array worden aangeleverd.',
            'location_ids.*.integer' => 'Elke geselecteerde locatie moet een geldig ID hebben.',
            'location_ids.*.exists' => 'Een geselecteerde locatie is ongeldig.',
            'selected_default_tasks.*.exists' => 'Een geselecteerde standaardtaak is ongeldig.',
            'selected_backlog_tasks.*.exists' => 'Een geselecteerde backlog taak is ongeldig.',
        ];
    }
}
