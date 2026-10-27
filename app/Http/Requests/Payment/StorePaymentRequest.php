<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Rental;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        $rental = $this->rental();
        $balance = $rental === null ? 0.0 : $rental->balanceDue();

        return [
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                function (string $attribute, mixed $value, callable $fail) use ($balance): void {
                    if ((float) $value > $balance) {
                        $fail('The payment is larger than the outstanding balance of '.number_format($balance, 0).' TZS. Record '.number_format($balance, 0).' TZS or less.');
                    }
                },
            ],
            'payment_method' => ['required', Rule::in(array_keys(PaymentMethod::options()))],
            'transaction_reference' => [
                $this->referenceRequired() ? 'required' : 'nullable',
                'string',
                'max:60',
            ],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.gt' => 'The payment amount must be greater than zero.',
            'transaction_reference.required' => 'Mobile money payments need the transaction reference so we can trace them.',
        ];
    }

    public function referenceRequired(): bool
    {
        $method = PaymentMethod::tryFrom((string) $this->input('payment_method'));

        if ($method === null) {
            return false;
        }

        return (new Payment(['payment_method' => $method]))->requiresTransactionReference();
    }

    private function rental(): ?Rental
    {
        $rental = $this->route('rental');

        return $rental instanceof Rental ? $rental : null;
    }
}
