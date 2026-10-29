<?php

namespace Tests\Feature;

use App\Enums\VehicleStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * FR14: sensitive actions must leave an append-only trail, and that trail must
 * be visible only to the system administrator.
 */
class AuditLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_successful_login_is_recorded_against_the_account(): void
    {
        $user = User::factory()->counterAgent()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $entry = AuditLog::query()->where('action', 'auth.login')->first();

        $this->assertNotNull($entry);
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame(User::class, $entry->subject_type);
        $this->assertSame($user->id, $entry->subject_id);
    }

    public function test_a_failed_login_is_recorded_without_an_actor(): void
    {
        $user = User::factory()->counterAgent()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $entry = AuditLog::query()->where('action', 'auth.login_failed')->first();

        $this->assertNotNull($entry);
        $this->assertNull($entry->user_id);
        $this->assertStringContainsString($user->email, $entry->description);
    }

    public function test_logging_out_is_recorded(): void
    {
        $user = User::factory()->counterAgent()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.logout',
            'user_id' => $user->id,
        ]);
    }

    public function test_a_sensitive_write_action_stores_the_acted_record_and_property_bag(): void
    {
        $staff = User::factory()->fleetStaff()->create();
        $vehicle = Vehicle::factory()->available()->create();

        $this->actingAs($staff)
            ->put(route('admin.vehicles.status', $vehicle), ['status' => VehicleStatus::Maintenance->value])
            ->assertRedirect();

        $entry = AuditLog::query()->where('action', 'vehicle.status_changed')->first();

        $this->assertNotNull($entry);
        $this->assertSame($staff->id, $entry->user_id);
        $this->assertSame(Vehicle::class, $entry->subject_type);
        $this->assertSame($vehicle->id, $entry->subject_id);
        $this->assertSame('available', $entry->properties['from']);
        $this->assertSame('maintenance', $entry->properties['to']);
    }

    public function test_an_administrator_can_browse_the_audit_trail(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::factory()->create([
            'action' => 'payment.recorded',
            'description' => 'Recorded 250000 TZS payment (receipt RCPT-AUDIT-MARKER).',
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit-log.index'))
            ->assertOk()
            ->assertSee('RCPT-AUDIT-MARKER');
    }

    public function test_the_trail_can_be_filtered_by_action(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::factory()->create(['action' => 'auth.login', 'description' => 'login entry marker']);
        AuditLog::factory()->create(['action' => 'vehicle.deleted', 'description' => 'vehicle deletion marker']);

        $this->actingAs($admin)
            ->get(route('admin.audit-log.index', ['action' => 'auth.login']))
            ->assertOk()
            ->assertSee('login entry marker')
            ->assertDontSee('vehicle deletion marker');
    }

    public function test_non_admin_staff_cannot_see_the_audit_trail(): void
    {
        $this->actingAs(User::factory()->counterAgent()->create())
            ->get(route('admin.audit-log.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->customer()->create())
            ->get(route('admin.audit-log.index'))
            ->assertForbidden();
    }
}
