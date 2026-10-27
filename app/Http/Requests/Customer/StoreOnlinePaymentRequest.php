<?php

namespace App\Http\Requests\Customer;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreOnlinePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Customer) ?? false;
    }

    public function rules(): array
    {
        return [
            'payment_method' => [
                'required',
                Rule::in(array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::onlineCapable())),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.required' => 'Choose how you would like to pay.',
            'payment_method.in' => 'That method cannot be paid online. Choose a card or mobile money option.',
        ];
    }
}
