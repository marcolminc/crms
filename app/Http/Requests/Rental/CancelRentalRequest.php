<?php

namespace App\Http\Requests\Rental;

use Illuminate\Foundation\Http\FormRequest;

final class CancelRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please record a reason for cancelling this rental.',
        ];
    }
}
