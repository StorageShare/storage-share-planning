<?php

namespace App\Http\Requests;

use App\Enums\VehicleType;
use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum as EnumRule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $vehicleId = $this->route('vehicle')->id ?? null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'license_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('vehicles', 'license_number')->ignore($vehicleId),
            ],
            'type' => ['required', new EnumRule(VehicleType::class)],
        ];
    }
}
