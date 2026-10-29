<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\FullName;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            'name' => ['required', 'string', 'max:255', new FullName],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'phone' => ['nullable', 'string', 'max:20', new PhoneNumber],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($target->is($this->user())) {
            $rules['role'] = [
                'required',
                Rule::enum(UserRole::class),
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
