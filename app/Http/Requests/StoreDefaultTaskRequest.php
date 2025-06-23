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
            'estimated_time_minutes' => 'nullable|integer|min:0',
            'applies_to_all_locations' => 'nullable|boolean',
            'applies_to_door_types' => 'nullable|boolean',
            'door_types' => 'nullable|array',
            'door_types.*' => 'string|max:255',
            'locations' => 'nullable|array',
            'locations.*' => 'integer|exists:locations,id',
            'benodigdheden' => 'nullable|array',
            'benodigdheden.*' => 'integer|exists:benodigdheden,id',
            'end_day_action_title' => 'nullable|string|max:255',
            'end_day_action_description' => 'nullable|string',
        ];
    }
}
