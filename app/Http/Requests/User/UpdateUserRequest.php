<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys(UserRole::options()))],
            'password' => ['nullable', 'string', 'confirmed', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($target->is($this->user())) {
            $rules['role'] = [
                'required',
                Rule::in(array_keys(UserRole::options())),
                function (string $attribute, mixed $value, \Closure $fail) use ($target): void {
                    if ($value !== $target->role?->value) {
                        $fail('You cannot change your own role.');
                    }
                },
            ];
            $rules['is_active'] = [
                'nullable',
                'boolean',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $fail('You cannot deactivate your own account.');
                    }
                },
            ];
        }

        return $rules;
    }
}
