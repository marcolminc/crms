<?php

namespace Database\Factories;

use App\Enums\GatewayCaptureOutcome;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentIntent>
 */
class PaymentIntentFactory extends Factory
{
    protected $model = PaymentIntent::class;

    public function definition(): array
    {
        return [
            'rental_id' => Rental::factory(),
            'tx_ref' => fn (array $attributes) => 'CRMS'.($attributes['rental_id'] ?? 0).'-'.substr((string) Str::ulid(), 0, 13),
            'checkout_url' => 'https://checkout.azampay.co.tz/pay/'.fake()->uuid(),
            'amount' => fake()->randomElement([50000, 120000, 200000]),
            'currency' => 'TZS',
            'payment_method' => PaymentMethod::Mpesa,
            'status' => PaymentIntentStatus::Pending,
            'gateway_tx_id' => null,
            'gateway_ref' => null,
            'payment_id' => null,
            'failure_reason' => null,
            'verified_at' => null,
        ];
    }

    /**
     * The customer was handed off to the hosted checkout.
     */
    public function started(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentIntentStatus::Started,
        ]);
    }

    /**
     * A live mobile money prompt is waiting on the customer's handset.
     */
    public function pushed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentIntentStatus::Started,
            'checkout_url' => null,
            'gateway_tx_id' => fake()->numerify('AZAMPAY-########'),
        ]);
    }

    /**
     * The gateway confirmed the charge and it reached the ledger.
     */
    public function recorded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentIntentStatus::Recorded,
            'gateway_tx_id' => fake()->numerify('########'),
            'gateway_ref' => 'AZAM-'.fake()->bothify('??##??##'),
            'payment_id' => fn (array $attributes) => Payment::factory()
                ->mobileMoney()
                ->create(['rental_id' => $attributes['rental_id']])
                ->id,
            'verified_at' => now(),
        ]);
    }

    public function failed(?GatewayCaptureOutcome $outcome = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentIntentStatus::Failed,
            'failure_reason' => ($outcome ?? GatewayCaptureOutcome::Declined)->value,
            'checkout_url' => null,
        ]);
    }
}
