<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    private static int $receiptSequence = 0;

    public function definition(): array
    {
        $reference = fake()->boolean()
            ? strtoupper(fake()->lexify('???')).fake()->numerify('######')
            : null;

        return [
            'rental_id' => Rental::factory(),
            'receipt_number' => $this->nextReceipt(),
            'type' => PaymentType::Payment,
            'parent_payment_id' => null,
            'amount' => fake()->randomElement([50000, 120000, 200000, 350000]),
            'payment_method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Pending,
            // transaction_reference is only meaningful for mobile money.
            'transaction_reference' => null,
            'recorded_by' => User::factory()->counterAgent(),
            'paid_at' => null,
            'notes' => null,
        ];
    }

    /**
     * A payment can never be larger than the rental it belongs to.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Payment $payment): void {
            $total = (float) $payment->rental->total_amount;
            if ((float) $payment->amount > $total) {
                $payment->forceFill(['amount' => $total])->save();
            }
        });
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Cash,
            'transaction_reference' => null,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::BankTransfer,
            'transaction_reference' => null,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    /**
     * A mobile money payment: always carries a transaction reference.
     */
    public function mobileMoney(?PaymentMethod $method = null): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => $method ?? PaymentMethod::Mpesa,
            'transaction_reference' => strtoupper(fake()->lexify('???')).fake()->numerify('######'),
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Partial,
            'paid_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Pending,
            'paid_at' => null,
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Refunded,
            'paid_at' => now(),
        ]);
    }

    /**
     * Money handed back to the customer for one of their payments.
     */
    public function refundOf(Payment $parent, float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'rental_id' => $parent->rental_id,
            'type' => PaymentType::Refund,
            'parent_payment_id' => $parent->id,
            'amount' => $amount,
            'payment_method' => $parent->payment_method,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    private function nextReceipt(): string
    {
        self::$receiptSequence++;

        return 'RC-'.today()->format('Ymd').'-'.str_pad((string) self::$receiptSequence, 4, '0', STR_PAD_LEFT);
    }
}
