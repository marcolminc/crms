<?php

namespace App\Http\Requests\Customer;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use App\Rules\FullName;
use App\Rules\Identifier;
use App\Rules\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        $customer = $this->route('customer');
        $customerId = $customer instanceof Customer ? $customer->id : null;

        return [
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('customers', 'user_id')->ignore($customerId),
            ],
            'national_id' => ['nullable', 'string', 'max:32', new Identifier],
            'passport_no' => ['nullable', 'string', 'max:32', new Identifier],
            'driving_license_no' => ['nullable', 'string', 'max:32', new Identifier],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:'.Carbon::today()->subYears(18)->toDateString()],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255', new FullName],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20', new PhoneNumber],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('user_id')) {
                return;
            }

            $linked = User::query()->find($this->integer('user_id'));
            if ($linked && $linked->role !== UserRole::Customer) {
                $validator->errors()->add('user_id', 'Only customer accounts can be linked to a customer profile.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'user_id.unique' => 'That account is already linked to another customer record.',
            'date_of_birth.before_or_equal' => 'The customer must be at least 18 years old.',
        ];
    }
}
