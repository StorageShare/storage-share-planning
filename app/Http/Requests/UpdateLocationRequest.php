<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'external_id' => 'nullable|integer',
            'sync_external_id' => 'nullable|integer',
            'address' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'city' => 'required|string|max:255',
            'zip_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'google_place_id' => 'nullable|string|max:255',
            'outdoor_safe_code' => 'nullable|string|max:255',
            'indoor_safe_code' => 'nullable|string|max:255',
            'outdoor_safe_content' => 'nullable|string',
            'indoor_safe_content' => 'nullable|string',
            'intratone_number' => 'nullable|string|max:255',
            'intratone_multiple_numbers' => 'nullable|string',
            'gate_number' => 'nullable|string|max:255',
            'lift' => 'nullable|string|max:255',
            'bv' => 'nullable|string|max:255',
            'type_deur' => 'nullable|string|max:255',
        ];
    }
}
