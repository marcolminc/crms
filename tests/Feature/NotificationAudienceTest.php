<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * FR1/roles: operational alerts must reach the desks that act on them —
 * not the administrator, who owns system concerns (users, roles, oversight),
 * and not any desk via a link that hops the reader to a different host.
 */
class NotificationAudienceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_new_reservation_reaches_booking_desk_only(): void
    {
        $user = User::factory()->customer()->create();
        Customer::factory()->create(['user_id' => $user->id]);
        $vehicle = Vehicle::factory()->available()->create();

        $agent = User::factory()->counterAgent()->create();
        $manager = User::factory()->branchManager()->create();
        $admin = User::factory()->admin()->create();
        $fleet = User::factory()->fleetStaff()->create();
        $finance = User::factory()->finance()->create();

        $this->actingAs($user)->post('/reservations', [
            'vehicle' => $vehicle->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-04',
        ]);

        $alerted = Notification::query()->where('type', 'reservation')->pluck('user_id')->all();
        sort($alerted);
        $expected = [$agent->id, $manager->id];
        sort($expected);
        $this->assertSame($expected, $alerted);
        $this->assertNotContains($admin->id, $alerted);
        $this->assertNotContains($fleet->id, $alerted);
        $this->assertNotContains($finance->id, $alerted);
    }

    public function test_staff_alert_links_are_stored_relative_to_the_host(): void
    {
        $user = User::factory()->customer()->create();
        Customer::factory()->create(['user_id' => $user->id]);
        $agent = User::factory()->counterAgent()->create();
        $vehicle = Vehicle::factory()->available()->create();

        $this->actingAs($user)->post('/reservations', [
            'vehicle' => $vehicle->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-04',
        ]);

        $link = Notification::where('user_id', $agent->id)->value('link');
        $this->assertStringStartsWith('/admin/reservations/', $link);

        // The relative link must actually resolve for the notified agent.
        $this->actingAs($agent)->get($link)->assertOk();
    }

    public function test_fleet_team_excludes_admin_and_booking_roles(): void
    {
        $fleet = User::factory()->fleetStaff()->create();
        $agent = User::factory()->counterAgent()->create();
        User::factory()->admin()->create();
        User::factory()->branchManager()->create();
        User::factory()->finance()->create();

        $ids = (new NotificationService)->fleetTeam();
        sort($ids);
        $expected = [$fleet->id, $agent->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_payment_desk_is_finance_plus_admin(): void
    {
        $finance = User::factory()->finance()->create();
        $admin = User::factory()->admin()->create();
        User::factory()->counterAgent()->create();

        $ids = (new NotificationService)->paymentDesk();
        sort($ids);
        $expected = [$finance->id, $admin->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_deactivated_staff_are_not_notified(): void
    {
        User::factory()->counterAgent()->create(['is_active' => false]);
        $active = User::factory()->branchManager()->create();

        $this->assertNotContains(
            User::query()->where('role', UserRole::CounterAgent)->value('id'),
            (new NotificationService)->bookingDesk(),
        );
        $this->assertContains($active->id, (new NotificationService)->bookingDesk());
    }
}
