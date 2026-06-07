<?php

namespace App\Http\Requests;

use App\Enums\VehicleType;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum as EnumRule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Routes are protected by is_admin middleware; allow here
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('license_number')) {
            $this->merge([
                'license_number' => Vehicle::normalizeLicenseNumber($this->input('license_number')),
            ]);
        }
    }

    /**
     * @return array<string, list<EnumRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:50', 'unique:vehicles,license_number'],
            'type' => ['required', new EnumRule(VehicleType::class)],
        ];
    }
}
