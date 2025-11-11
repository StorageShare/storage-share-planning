<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreRequirementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        return $user && $user->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:requirements,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'required_for_locations' => ['nullable', 'array'],
            'required_for_locations.*' => ['integer', 'exists:locations,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'De naam is verplicht.',
            'name.unique' => 'Er bestaat al een benodigdheid met deze naam.',
            'name.max' => 'De naam mag niet langer zijn dan 255 karakters.',
            'description.max' => 'De beschrijving mag niet langer zijn dan 1000 karakters.',
            'required_for_locations.*.exists' => 'Een van de geselecteerde locaties bestaat niet.',
        ];
    }
}
