<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    public function authCredentials(): array
    {
        return [
            'email' => $this->validated('email'),
            'password' => $this->validated('password'),
        ];
    }

    public function throttleKey(): string
    {
        return 'login:'.$this->ip().':'.$this->validated('email');
    }
}
