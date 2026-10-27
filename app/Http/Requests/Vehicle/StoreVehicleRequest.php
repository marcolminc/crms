<?php

namespace App\Http\Requests\Vehicle;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('plate_number')) {
            $this->merge([
                'plate_number' => strtoupper(preg_replace('/\s+/', ' ', trim($this->input('plate_number')))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'plate_number' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9\/\- ]{4,20}$/',
                Rule::unique('vehicles', 'plate_number')->whereNull('deleted_at'),
            ],
            'make' => ['required', 'string', 'max:60'],
            'model' => ['required', 'string', 'max:60'],
            'year' => ['required', 'integer', 'between:1980,'.(date('Y') + 1)],
            'type' => ['required', Rule::in(array_keys(VehicleType::options()))],
            'seats' => ['nullable', 'integer', 'min:1', 'max:60'],
            'transmission' => ['required', Rule::in(['manual', 'automatic'])],
            'fuel_type' => ['required', Rule::in(['petrol', 'diesel', 'hybrid', 'electric'])],
            'colour' => ['nullable', 'string', 'max:40'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'rate_per_day' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'status' => ['nullable', Rule::in(array_map(fn (VehicleStatus $s) => $s->value, VehicleStatus::manuallyAssignable()))],
            'description' => ['nullable', 'string', 'max:5000'],
            'features' => ['nullable', 'string', 'max:2000'],
            'image_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'plate_number.regex' => 'The plate number may only contain letters, digits, spaces, hyphens and slashes (4-20 characters).',
            'plate_number.unique' => 'A vehicle with this plate number already exists.',
            'rate_per_day.gt' => 'The daily rate must be greater than zero.',
        ];
    }
}
