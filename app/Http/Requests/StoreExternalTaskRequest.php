<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'feedback_information' => 'nullable|string|max:255',
            'feedback_owner_name' => 'nullable|string|max:255',
            'feedback_emails' => 'nullable|string|max:255',
            'location_id' => [
                'required_without:location_external_id',
                'integer',
                Rule::exists(Location::class, 'id'),
            ],
            'location_external_id' => 'required_without:location_id|integer',
            'deadline' => 'nullable|date',
            'external_deadline_at' => 'nullable|date',
            'estimated_time_minutes' => 'nullable|integer|min:0|max:99999',
            'priority' => ['nullable', Rule::in(TaskPriority::values())],
        ];
    }
}
