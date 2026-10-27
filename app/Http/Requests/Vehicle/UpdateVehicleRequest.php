<?php

namespace App\Http\Requests\Vehicle;

use App\Enums\VehicleStatus;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends StoreVehicleRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['plate_number'][4] = Rule::unique('vehicles', 'plate_number')
            ->ignore($this->route('vehicle'))
            ->whereNull('deleted_at');

        $allowed = array_map(fn (VehicleStatus $s) => $s->value, VehicleStatus::manuallyAssignable());
        $current = $this->route('vehicle')->status;
        if ($current !== null && ! in_array($current->value, $allowed, true)) {
            $allowed[] = $current->value;
        }
        $rules['status'] = ['nullable', Rule::in($allowed)];

        return $rules;
    }

    public function messages(): array
    {
        $messages = parent::messages();
        $messages['plate_number.unique'] = 'Another vehicle already uses this plate number.';

        return $messages;
    }
}
