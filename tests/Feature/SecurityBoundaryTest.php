<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SecurityBoundaryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const NAME_PAYLOAD = '<script>alert("taken")</script>';

    private const NOTE_PAYLOAD = '<img src=x onerror=alert(1)>';

    private function staff(): User
    {
        return User::factory()->counterAgent()->create();
    }

    public function test_customer_names_are_escaped_wherever_they_are_rendered(): void
    {
        $user = User::factory()->customer()->create(['name' => self::NAME_PAYLOAD]);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $staff = $this->staff();

        $pages = [
            $this->actingAs($user)->get(route('profile.edit')),
            $this->actingAs($staff)->get(route('admin.customers.index')),
            $this->actingAs($staff)->get(route('admin.customers.show', $customer)),
            $this->actingAs($staff)->get(route('admin.customers.history', $customer)),
        ];

        foreach ($pages as $page) {
            $page->assertOk()
                ->assertSee('&lt;script&gt;alert(&quot;taken&quot;)&lt;/script&gt;', false)
                ->assertDontSee('<script>alert("taken")</script>', false);
        }
    }

    public function test_reservation_notes_are_escaped_for_both_sides_of_the_booking(): void
    {
        $user = User::factory()->customer()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $reservation = Reservation::factory()->pending()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => Vehicle::factory()->available()->create()->id,
            'notes' => self::NOTE_PAYLOAD,
        ]);

        $this->actingAs($user)
            ->get(route('customer.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);

        $this->actingAs($this->staff())
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_a_customer_cannot_grant_themselves_a_staff_role_through_the_profile_form(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Hakim Juma',
            'email' => 'hakim@example.com',
            'role' => 'admin',
            'is_active' => false,
            'id' => 1,
        ])->assertRedirect(route('profile.edit'));

        $fresh = $user->fresh();

        $this->assertSame('customer', $fresh->role->value);
        $this->assertTrue($fresh->is_active);
        $this->assertSame($user->id, $fresh->id);
    }

    public function test_a_search_term_cannot_escape_the_query_and_return_every_record(): void
    {
        $customer = Customer::factory()->create(['national_id' => '99887766']);
        $customer->user->update(['name' => 'Amina Yusuf']);

        $this->actingAs($this->staff())
            ->get(route('admin.customers.index', ['q' => "amina' OR '1'='1"]))
            ->assertOk()
            ->assertDontSee('Amina Yusuf');

        $this->actingAs($this->staff())
            ->get(route('admin.customers.index', ['q' => 'Amina']))
            ->assertOk()
            ->assertSee('Amina Yusuf');
    }

    public function test_a_malformed_filter_value_is_treated_as_data_and_not_as_sql(): void
    {
        Vehicle::factory()->available()->create(['plate_number' => 'T101 DSH']);

        $this->actingAs($this->staff())
            ->get(route('admin.vehicles.index', [
                'status' => 'available) OR 1=1 --',
                'q' => "' OR '1'='1",
            ]))
            ->assertOk()
            ->assertDontSee('T101 DSH');

        $this->assertSame(1, Vehicle::query()->count());
    }

    public function test_csv_exports_neutralise_formula_injection(): void
    {
        $user = User::factory()->customer()->create(['name' => '=SUM(A1:A2)']);
        Customer::factory()->create(['user_id' => $user->id]);

        $csv = $this->actingAs($this->staff())
            ->get(route('admin.reports.show', ['report' => 'customers', 'format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=SUM(A1:A2)", $csv);
        $this->assertSame(1, substr_count($csv, '=SUM(A1:A2)'));
    }
}
