<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Location;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExternalTaskWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|Rule|string|array<int, ValidationRule|Rule|string>>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'feedback_information' => 'nullable|string|max:255',
            'feedback_owner_name' => 'nullable|string|max:255',
            'feedback_emails' => 'nullable|string|max:255',
            'location_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists(Location::class, 'id'),
            ],
            'external_deadline_at' => 'nullable|date',
            'estimated_time_minutes' => 'nullable|integer|min:0|max:99999',
            'status' => ['sometimes', 'required', Rule::in(TaskStatus::values())],
            'priority' => ['sometimes', 'required', Rule::in(TaskPriority::values())],
        ];
    }
}
