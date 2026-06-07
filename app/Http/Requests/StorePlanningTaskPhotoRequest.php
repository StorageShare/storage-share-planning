<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePlanningTaskPhotoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Autorisatielogica kan hier worden toegevoegd, bijv. controleren of de gebruiker
        // eigenaar is van de planningstaak of de juiste permissies heeft.
        // Voor nu, laten we het open voor ingelogde gebruikers (indien API-authenticatie actief is).
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:20480', // Max 20MB - will be compressed to 2MB
            // Optioneel: velden voor bijschrift, etc.
            // 'caption' => 'nullable|string|max:255',
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
            'photo.required' => 'Een fotobestand is verplicht.',
            'photo.image' => 'Het bestand moet een afbeelding zijn.',
            'photo.mimes' => 'De foto moet een JPG, JPEG, PNG, GIF of WebP zijn.',
            'photo.max' => 'De foto mag maximaal 20MB groot zijn (wordt automatisch verkleind naar 2MB).',
        ];
    }
}
