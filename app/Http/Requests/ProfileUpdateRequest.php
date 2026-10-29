<?php

namespace App\Http\Requests;

use App\Rules\FullName;
use App\Rules\Identifier;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new FullName],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => ['nullable', 'string', 'max:20', new PhoneNumber],
            'national_id' => ['nullable', 'string', 'max:32', new Identifier],
            'passport_no' => ['nullable', 'string', 'max:32', new Identifier],
            'driving_license_no' => ['nullable', 'string', 'max:32', new Identifier],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255', new FullName],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20', new PhoneNumber],
        ];
    }

    public function attributes(): array
    {
        return [
            'national_id' => 'national ID',
            'passport_no' => 'passport number',
            'driving_license_no' => 'driving licence number',
            'date_of_birth' => 'date of birth',
            'emergency_contact_name' => 'emergency contact name',
            'emergency_contact_phone' => 'emergency contact phone',
        ];
    }
}
