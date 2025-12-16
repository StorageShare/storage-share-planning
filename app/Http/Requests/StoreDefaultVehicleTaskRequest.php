<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDefaultVehicleTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled by routes/middleware; allow request validation.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'estimated_time_minutes' => 'nullable|integer|min:0',
            'active' => 'nullable|boolean',
        ];
    }
}
