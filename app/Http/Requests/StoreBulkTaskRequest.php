<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'feedback_information' => 'nullable|string|max:255',
            'deadline' => 'nullable|date',
            'estimated_time_minutes' => 'nullable|integer|min:0|max:99999',
            'priority' => ['nullable', Rule::in(TaskPriority::values())],
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'image|mimes:jpg,jpeg,png,gif,webp|max:20480',
            'requirements' => 'nullable|array',
            'requirements.*' => 'integer|exists:requirements,id',
            'end_day_action_title' => 'nullable|string|max:255',
            'end_day_action_description' => 'nullable|string',
            'is_recurring' => 'nullable|boolean',
            'recurring_interval_type' => 'nullable|required_if:is_recurring,1|in:days,weeks,months,years',
            'recurring_interval_value' => 'nullable|required_if:is_recurring,1|integer|min:1|max:365',

            // Selection rules (similar to DefaultTask)
            'applies_to_all_locations' => 'nullable|boolean',
            'applies_to_lift_locations' => 'nullable|boolean',
            'applies_to_door_types' => 'nullable|boolean',
            'door_types' => 'nullable|array',
            'door_types.*' => 'string|max:255',
            'locations' => 'nullable|array',
            'locations.*' => 'integer|exists:locations,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->input('priority')) {
            $this->merge(['priority' => TaskPriority::NORMAL->value]);
        }
    }
}
