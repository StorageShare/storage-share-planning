<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDefaultTaskRequest extends FormRequest
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
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'feedback_information' => 'nullable|string|max:255',
            'estimated_time_minutes' => 'sometimes|nullable|integer|min:0',
            'is_always_included' => 'sometimes|nullable|boolean',
            'applies_to_all_locations' => 'sometimes|nullable|boolean',
            'applies_to_door_types' => 'sometimes|nullable|boolean',
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
