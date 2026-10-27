<?php

namespace Tests\Feature\Models;

use App\Models\Reservation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_days_counts_both_pickup_and_dropoff_days(): void
    {
        $reservation = Reservation::factory()->create([
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-03',
        ]);

        $this->assertSame(3, $reservation->days());
    }

    public function test_same_day_booking_counts_as_one_day(): void
    {
        $reservation = Reservation::factory()->create([
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-01',
        ]);

        $this->assertSame(1, $reservation->days());
    }

    public function test_conflict_is_detected_against_a_live_reservation(): void
    {
        $existing = Reservation::factory()->pending()->create([
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-10',
        ]);

        $overlap = Reservation::factory()->make([
            'vehicle_id' => $existing->vehicle_id,
            'start_date' => '2026-11-09',
            'end_date' => '2026-11-15',
        ]);

        $this->assertTrue($overlap->conflictsWithLiveBookings());
    }

    public function test_adjacent_periods_do_not_conflict(): void
    {
        $existing = Reservation::factory()->confirmed()->create([
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
        ]);

        $next = Reservation::factory()->make([
            'vehicle_id' => $existing->vehicle_id,
            'start_date' => '2026-11-06',
            'end_date' => '2026-11-08',
        ]);

        $this->assertFalse($next->conflictsWithLiveBookings());
    }

    public function test_cancelled_reservations_are_not_live_and_block_nothing(): void
    {
        $dead = Reservation::factory()->cancelled()->create([
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-10',
        ]);

        $candidate = Reservation::factory()->make([
            'vehicle_id' => $dead->vehicle_id,
            'start_date' => '2026-11-07',
            'end_date' => '2026-11-09',
        ]);

        $this->assertFalse($candidate->conflictsWithLiveBookings());
    }
}
