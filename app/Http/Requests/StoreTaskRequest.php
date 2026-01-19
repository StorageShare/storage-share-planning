<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest; // Import Location model for exists rule
use Illuminate\Validation\Rule; // Import Rule facade

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Implement proper authorization later (e.g., user can create tasks for this location)
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
            'location_id' => [
                'required',
                'integer',
                Rule::exists(Location::class, 'id'), // Check if location_id exists in locations table
            ],
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
            // 'status' will likely be defaulted in the model or migration, not required here initially
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If location_id is coming from the route parameters (for nested resources)
        if ($this->route('location') && ! $this->input('location_id')) {
            $this->merge([
                'location_id' => $this->route('location')->id,
            ]);
        }

        // Set default priority if not provided
        if (! $this->input('priority')) {
            $this->merge(['priority' => TaskPriority::NORMAL->value]);
        }
    }
}
