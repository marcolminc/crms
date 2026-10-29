<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Models\ChargeApproval;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargeApproval>
 */
class ChargeApprovalFactory extends Factory
{
    protected $model = ChargeApproval::class;

    public function definition(): array
    {
        return [
            'rental_id' => Rental::factory(),
            'type' => ApprovalType::Discount,
            'amount' => fake()->randomElement([250000, 300000, 500000]),
            'reason' => fake()->sentence(6),
            'status' => ApprovalStatus::Pending,
            'requested_by' => User::factory()->counterAgent(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ];
    }

    public function damage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ApprovalType::Damage,
        ]);
    }

    public function discount(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ApprovalType::Discount,
        ]);
    }
}
