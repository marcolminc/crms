<?php

namespace App\Http\Requests\Rental;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UC23: an agent or manager assigns (or clears) the chauffeur on a rental.
 * The driver must be an active Driver account; the controller additionally
 * checks that the driver is free for the hire's dates.
 */
final class AssignDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(
            \App\Enums\UserRole::CounterAgent,
            \App\Enums\UserRole::BranchManager,
            \App\Enums\UserRole::Admin
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            // An empty string means "no driver" (clear the assignment).
            'driver' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', \App\Enums\UserRole::Driver->value)->where('is_active', true),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'driver.exists' => 'Choose an active driver account.',
        ];
    }
}
