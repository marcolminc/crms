<?php

namespace App\Http\Requests\Vehicle;

use App\Enums\VehicleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateVehicleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_map(fn (VehicleStatus $s) => $s->value, VehicleStatus::manuallyAssignable()))],
        ];
    }
}
