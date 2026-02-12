<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDefaultTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // For now, allow anyone. Implement proper authorization later.
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
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'feedback_information' => 'nullable|string|max:255',
            'estimated_time_minutes' => 'nullable|integer|min:0',
            'is_photo_required' => 'nullable|boolean',
            'time_calculation_type' => 'required|string|in:simplified,advanced',
            'time_per_m2_minutes' => 'nullable|numeric|min:0',
            'base_time_minutes' => 'nullable|integer|min:0',
            'has_lift_extra_minutes' => 'nullable|integer|min:0',
            'no_lift_extra_minutes' => 'nullable|integer|min:0',
            'is_always_included' => 'nullable|boolean',
            'applies_to_all_locations' => 'nullable|boolean',
            'applies_to_lift_locations' => 'nullable|boolean',
            'applies_to_door_types' => 'nullable|boolean',
            'door_types' => 'nullable|array',
            'door_types.*' => 'string|max:255',
            'locations' => 'nullable|array',
            'locations.*' => 'integer|exists:locations,id',
            'requirements' => 'nullable|array',
            'requirements.*' => 'integer|exists:requirements,id',
            'end_day_action_title' => 'nullable|string|max:255',
            'end_day_action_description' => 'nullable|string',
        ];
    }
}
