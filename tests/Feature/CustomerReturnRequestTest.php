<?php

namespace Tests\Feature;

use App\Enums\RentalStatus;
use App\Models\Customer;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * UC09 (customer side): the driver can tell the desk a vehicle has been dropped
 * off. It is only a request - the formal return still needs the staff inspection.
 */
class CustomerReturnRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_customer_can_report_an_open_rental_as_returned(): void
    {
        $rental = Rental::factory()->active()->create();
        $agent = User::factory()->counterAgent()->create();

        $this->actingAs($rental->customer->user)
            ->post(route('customer.rentals.return-request', $rental))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $rental->refresh();
        $this->assertNotNull($rental->return_requested_at);
        $this->assertSame(RentalStatus::Active, $rental->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $agent->id,
            'type' => 'return_requested',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rental.return_requested']);
    }

    public function test_the_return_request_cannot_be_raised_twice(): void
    {
        $rental = Rental::factory()->active()->create(['return_requested_at' => now()]);

        $this->actingAs($rental->customer->user)
            ->post(route('customer.rentals.return-request', $rental))
            ->assertSessionHas('error');
    }

    public function test_a_completed_rental_cannot_be_requested_as_returned(): void
    {
        $rental = Rental::factory()->completed()->create();

        $this->actingAs($rental->customer->user)
            ->post(route('customer.rentals.return-request', $rental))
            ->assertSessionHas('error');
    }

    public function test_a_customer_cannot_raise_a_request_on_another_customers_rental(): void
    {
        $rental = Rental::factory()->active()->create();
        $intruder = Customer::factory()->create()->user;

        $this->actingAs($intruder)
            ->post(route('customer.rentals.return-request', $rental))
            ->assertNotFound();
    }
}
