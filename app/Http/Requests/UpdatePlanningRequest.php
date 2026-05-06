<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use App\Models\DefaultTask;
use App\Models\Location;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanningRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $startAddress = $this->input('start_address_option');
        if ($startAddress === 'Anders') {
            $startAddress = $this->input('start_address_custom');
        }

        $this->merge([
            'start_address' => $startAddress,
        ]);
    }

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
            'vehicle_id' => [
                'required',
                'integer',
                Rule::exists(\App\Models\Vehicle::class, 'id'),
                function ($attribute, $value, $fail) use ($planning_id) {
                    $date = $this->input('planned_date');
                    if (!$date) {
                        return; // other rule will handle
                    }
                    $exists = \App\Models\Planning::query()
                        ->whereDate('planned_date', $date)
                        ->where('vehicle_id', $value)
                        ->when($planning_id, fn($q) => $q->where('id', '!=', $planning_id))
                        // Allow if any existing planning(s) on that date for this vehicle are completed
                        ->where('status', '!=', 'completed')
                        ->exists();
                    if ($exists) {
                        $fail('Dit voertuig is al gekoppeld aan een planning op deze datum.');
                    }
                }
            ],
            'notes' => 'nullable|string',
            'start_address_option' => 'required|string',
            'start_address_custom' => 'nullable|string|required_if:start_address_option,Anders|max:255',
            'start_address' => 'required|string|max:255',
            'start_time' => 'nullable|date_format:H:i',
            'user_ids' => 'nullable|array',
            'user_ids.*' => ['integer', Rule::exists(\App\Models\User::class, 'id')],
            'selected_default_tasks' => 'nullable|array',
            'selected_default_tasks.*' => [
                'integer',
                Rule::exists(DefaultTask::class, 'id'),
                function ($attribute, $value, $fail) use ($selected_location_ids) {
                    $defaultTask = DefaultTask::with('locations')->find($value);
                    // Check of de default task gekoppeld is aan minstens één van de geselecteerde locaties
                    if (! $defaultTask || $defaultTask->locations->pluck('id')->intersect($selected_location_ids)->isEmpty()) {
                        $fail("De geselecteerde standaardtaak '{$defaultTask->title}' hoort niet bij de gekozen locatie(s).");
                    }
                },
            ],
            'selected_backlog_tasks' => 'nullable|array',
            'selected_backlog_tasks.*' => [
                'integer',
                Rule::exists(Task::class, 'id'),
                function ($attribute, $value, $fail) use ($planning_id, $selected_location_ids) {
                    $task = Task::find($value);
                    if (! $task) {
                        $fail("De geselecteerde backlog taak ({$value}) bestaat niet.");

                        return;
                    }
                    // Valideer dat de backlog taak bij één van de geselecteerde locaties hoort
                    if (! in_array($task->location_id, $selected_location_ids)) {
                        $fail("De geselecteerde backlog taak '{$task->title}' hoort niet bij de gekozen locatie(s).");

                        return;
                    }
                    // STATUSVALIDATIE VERSOEPELD: status maakt niet meer uit bij bewerken
                    // Behoud bestaand beleid: OPEN taken mogen aan meerdere planningen gekoppeld worden.
                    // Alleen wanneer de taak NIET OPEN is, blokkeren we koppeling aan een ANDERE planning (niet deze).
                    if ($task->status !== TaskStatus::OPEN) {
                        $query = $task->planningTasks()->whereHas('planning', function ($q) use ($planning_id) {
                            if ($planning_id) {
                                $q->where('id', '!=', $planning_id);
                            }
                            // Eventueel kun je hier nog filteren op actieve planningen
                        });
                        if ($query->exists()) {
                            $fail("De backlog taak '{$task->title}' is al aan een andere planning toegewezen.");
                        }
                    }
                },
            ],
            'check_inactive_spaces' => 'nullable|array',
            'check_inactive_spaces.*' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Een voertuig is verplicht voor een planning.',
            'vehicle_id.exists' => 'Het geselecteerde voertuig bestaat niet.',
            'selected_default_tasks.*.exists' => 'Een geselecteerde standaardtaak is ongeldig.',
            'selected_backlog_tasks.*.exists' => 'Een geselecteerde backlog taak is ongeldig.',
        ];
    }
}
