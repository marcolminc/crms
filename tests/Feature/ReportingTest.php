<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Models\Customer;
use App\Models\Maintenance;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    public function test_the_admin_dashboard_summarises_fleet_and_trade(): void
    {
        $vehicle = Vehicle::factory()->available()->create(['plate_number' => 'T424 DSH']);
        Reservation::factory()->pending()->create(['vehicle_id' => $vehicle->id]);
        $overdue = Rental::factory()->create([
            'vehicle_id' => $vehicle->id,
            'status' => RentalStatus::Active,
            'start_date' => now()->subDays(4)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'days' => 4,
        ]);

        $this->actingAs($this->manager())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Fleet by status')
            ->assertSee($overdue->vehicle->plate_number);
    }

    public function test_every_report_renders_and_rejects_unknown_keys(): void
    {
        $manager = $this->manager();

        $rental = Rental::factory()->completed()->create([
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'days' => 1,
            'rate_per_day' => 100000,
            'additional_charges' => 0,
            'discount' => 0,
        ]);
        $rental->forceFill(['total_amount' => 100000])->save();
        $rental->payments()->create([
            'receipt_number' => 'RC-REPORT-1',
            'amount' => 100000,
            'payment_method' => 'cash',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
        $rental->vehicleReturn()->create([
            'vehicle_id' => $rental->vehicle_id,
            'returned_at' => now(),
            'condition' => 'good',
            'fuel_level' => 'full',
            'mileage_at_return' => 100000,
            'additional_charges' => 0,
            'processed_by' => $manager->id,
        ]);
        Maintenance::factory()->completed()->create(['vehicle_id' => $rental->vehicle_id]);

        foreach (['fleet', 'reservations', 'rentals', 'payments', 'customers', 'returns', 'maintenance'] as $report) {
            $this->actingAs($manager)
                ->get(route('admin.reports.show', $report))
                ->assertOk();
        }

        $this->actingAs($manager)->get(route('admin.reports.show', 'nope'))->assertNotFound();
    }

    public function test_reports_can_be_downloaded_as_csv(): void
    {
        Vehicle::factory()->available()->create(['plate_number' => 'T777 CSV']);

        $response = $this->actingAs($this->manager())
            ->get(route('admin.reports.show', 'fleet').'?format=csv');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('T777 CSV', $response->streamedContent());
    }

    public function test_the_rentals_report_counts_only_hires_inside_the_period(): void
    {
        $past = Rental::factory()->completed()->create([
            'start_date' => '2020-01-01',
            'end_date' => '2020-01-02',
            'days' => 2,
            'rate_per_day' => 100000,
        ]);
        $past->forceFill(['total_amount' => 200000])->save();

        $recent = Rental::factory()->completed()->create([
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'days' => 1,
            'rate_per_day' => 100000,
        ]);
        $recent->forceFill(['total_amount' => 100000])->save();

        $this->actingAs($this->manager())
            ->get(route('admin.reports.show', 'rentals'))
            ->assertOk()
            ->assertSee('100,000')
            ->assertDontSee('200,000');
    }

    public function test_the_customers_report_lists_registered_accounts(): void
    {
        $user = User::factory()->customer()->create(['name' => 'Amina Juma']);
        Customer::factory()->create(['user_id' => $user->id]);

        $this->actingAs($this->manager())
            ->get(route('admin.reports.show', 'customers'))
            ->assertOk()
            ->assertSee('Amina Juma');
    }

    public function test_staff_can_review_a_customers_full_rental_history(): void
    {
        $manager = $this->manager();
        $customer = Customer::factory()->create(['user_id' => User::factory()->customer()->create(['name' => 'Joseph Mwakalinga'])->id]);
        Rental::factory()->completed()->create([
            'customer_id' => $customer->id,
            'status' => RentalStatus::Completed,
        ]);

        $this->actingAs($manager)->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Joseph Mwakalinga');

        $this->actingAs($manager)->get(route('admin.customers.history', $customer))
            ->assertOk();
    }
}
