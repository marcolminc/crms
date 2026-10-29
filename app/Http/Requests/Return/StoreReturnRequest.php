<?php

namespace App\Http\Requests\Return;

use App\Enums\VehicleCondition;
use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'returned_at' => ['nullable', 'date'],
            'condition' => ['required', Rule::in(array_keys(VehicleCondition::options()))],
            'fuel_level' => ['nullable', 'string', 'max:20'],
            'mileage_at_return' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'damage_notes' => ['nullable', 'string', 'max:5000'],
            'additional_charges' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            // Inspection evidence taken as the car comes back.
            'photos' => ['nullable', 'array'],
        ];

        foreach (array_keys(Vehicle::SLOTS) as $slot) {
            $rules["photos.$slot"] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [];
        foreach (Vehicle::SLOTS as $slot => $label) {
            $messages["photos.$slot.image"] = "The {$label} return photo must be an image file.";
            $messages["photos.$slot.mimes"] = "The {$label} return photo must be a JPG, PNG or WebP file.";
            $messages["photos.$slot.max"] = "The {$label} return photo must be 4 MB or smaller.";
        }

        return $messages;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('returned_at')) {
                $returnedAt = Carbon::parse((string) $this->input('returned_at'));
                if ($returnedAt->gt(Carbon::now())) {
                    $validator->errors()->add('returned_at', 'The return time cannot be in the future.');
                }
            }

            $rental = $this->route('rental');
            if ($rental instanceof Rental
                && $this->filled('mileage_at_return')
                && $rental->vehicle
                && $rental->vehicle->mileage !== null
                && (int) $this->input('mileage_at_return') < (int) $rental->vehicle->mileage) {
                $validator->errors()->add(
                    'mileage_at_return',
                    'Return mileage cannot be lower than the vehicle\'s recorded mileage ('
                    .number_format((int) $rental->vehicle->mileage).' km).'
                );
            }
        });
    }
}
