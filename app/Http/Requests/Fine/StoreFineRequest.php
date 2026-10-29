<?php

namespace App\Http\Requests\Fine;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

final class StoreFineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::CounterAgent, UserRole::BranchManager, UserRole::Finance, UserRole::Admin) ?? false;
    }

    public function rules(): array
    {
        return [
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'issued_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Enter the fine amount so liability can be decided.',
        ];
    }
}
