<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use App\Models\DefaultTask;
use App\Models\Location;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanningRequest extends FormRequest
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
        return [
            'location_ids' => 'required|array|min:1',
            'location_ids.*' => ['integer', Rule::exists(Location::class, 'id')],
            'planned_date' => 'required|date',
            'vehicle_id' => [
                'required',
                'integer',
                Rule::exists(\App\Models\Vehicle::class, 'id'),
                function ($attribute, $value, $fail) {
                    // Vehicle must be available (not used in another planning on the same date)
                    $date = $this->input('planned_date');
                    if (! $date) {
                        return; // other rule will report missing date
                    }
                    $exists = \App\Models\Planning::query()
                        ->whereDate('planned_date', $date)
                        ->where('vehicle_id', $value)
                        // Allow if the only planning(s) on that date are completed
                        ->where(function ($q) {
                            $q->whereNull('status')
                                ->orWhere('status', '!=', 'completed');
                        })
                        ->exists();
                    if ($exists) {
                        $fail('Dit voertuig is al gekoppeld aan een planning op deze datum.');
                    }
                },
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
                function ($attribute, $value, $fail) {
                    $defaultTask = DefaultTask::find($value);
                    /** @var array<int, int> $ids */
                    $ids = is_array($this->input('location_ids')) ? $this->input('location_ids') : [];
                    $selected_location_ids = collect($ids);
                    if ($defaultTask && ! $defaultTask->locations()->whereIn('locations.id', $selected_location_ids)->exists()) {
                        $fail('De geselecteerde standaardtaak ('.$defaultTask->title.') hoort niet bij één van de gekozen locaties.');
                    }
                },
            ],
            'selected_backlog_tasks' => 'nullable|array',
            'selected_backlog_tasks.*' => [
                'integer',
                Rule::exists(Task::class, 'id'),
                function ($attribute, $value, $fail) {
                    $task = Task::find($value);
                    if (! $task) {
                        $fail('De geselecteerde backlog taak ('.$value.') bestaat niet.');

                        return;
                    }
                    $selected_location_ids = $this->input('location_ids', []);
                    if (! in_array($task->location_id, $selected_location_ids)) {
                        $fail("De geselecteerde backlog taak '{$task->title}' hoort niet bij één van de gekozen locaties.");

                        return;
                    }
                    if (! in_array($task->status, [TaskStatus::OPEN, TaskStatus::IN_PROGRESS])) {
                        $fail("De geselecteerde backlog taak '{$task->title}' heeft niet de status open of in uitvoering. Status is: ".$task->status->label());

                        return;
                    }
                    if ($task->planningTasks()->whereHas('planning')->exists()) {
                        // Verfijning nodig voor "actieve" planningen als dat relevant wordt.
                        // Voor nu: als het al in *enige* planning zit, niet opnieuw selecteerbaar in een nieuwe planning.
                        // Dit is een conservatieve benadering.
                        // $fail("De backlog taak '{$task->title}' is al aan een planning toegewezen.");
                    }
                },
            ],
            'check_inactive_spaces' => 'nullable|array',
            'check_inactive_spaces.*' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location_ids.required' => 'Minstens één locatie moet geselecteerd zijn.',
            'location_ids.array' => 'Locaties moeten als een array worden aangeleverd.',
            'location_ids.*.integer' => 'Elke geselecteerde locatie moet een geldig ID hebben.',
            'location_ids.*.exists' => 'Een geselecteerde locatie is ongeldig.',
            'vehicle_id.required' => 'Een voertuig is verplicht voor een planning.',
            'vehicle_id.exists' => 'Het geselecteerde voertuig bestaat niet.',
            'selected_default_tasks.*.exists' => 'Een geselecteerde standaardtaak is ongeldig.',
            'selected_backlog_tasks.*.exists' => 'Een geselecteerde backlog taak is ongeldig.',
        ];
    }
}
