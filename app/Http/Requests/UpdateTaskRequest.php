<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
// use App\Enums\TaskStatusEnum; // Voorbeeld als je een Enum voor status zou gebruiken
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Implement proper authorization later (e.g., user can update this task)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // In een 'update' request zijn velden vaak 'sometimes' required,
        // wat betekent dat ze alleen gevalideerd worden als ze aanwezig zijn in de request.
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'deadline' => 'nullable|date',
            'estimated_time_minutes' => 'nullable|integer|min:0|max:99999',
            'status' => [
                'sometimes',
                'required',
                'string',
                // Voorbeeld als je een Enum zou gebruiken:
                // Rule::in(array_column(TaskStatusEnum::cases(), 'value'))
                Rule::in(['open', 'in_progress', 'completed']), // Pas aan met je daadwerkelijke statussen
            ],
            'priority' => ['sometimes', 'required', Rule::in(TaskPriority::values())],
            'photos' => 'nullable|array|max:10',
            'photos.*' => 'image|mimes:jpg,jpeg,png,gif,webp|max:20480',
            'requirements' => 'nullable|array',
            'requirements.*' => 'integer|exists:requirements,id',
            'end_day_action_title' => 'nullable|string|max:255',
            'end_day_action_description' => 'nullable|string',
            'is_recurring' => 'nullable|boolean',
            'recurring_interval_type' => 'nullable|required_if:is_recurring,1|in:days,weeks,months,years',
            'recurring_interval_value' => 'nullable|required_if:is_recurring,1|integer|min:1|max:365',
        ];
    }
}
