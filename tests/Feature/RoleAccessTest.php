<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/profile')->assertRedirect(route('login'));
    }

    public function test_public_pages_stay_open_to_guests(): void
    {
        $this->get('/')->assertOk();
        $this->get('/vehicles')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_customers_cannot_reach_the_staff_area(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get('/admin')->assertForbidden();
        $this->actingAs($customer)->get('/admin/rentals')->assertForbidden();
    }

    public function test_staff_cannot_reach_the_customer_area(): void
    {
        $staff = User::factory()->counterAgent()->create();

        $this->actingAs($staff)->get('/dashboard')->assertForbidden();
        $this->actingAs($staff)->get('/reservations')->assertForbidden();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function staffRoleStates(): array
    {
        return [
            'system administrator' => ['admin'],
            'fleet manager' => ['fleetStaff'],
            'rental staff' => ['counterAgent'],
            'finance' => ['finance'],
            'branch manager' => ['branchManager'],
        ];
    }

    #[DataProvider('staffRoleStates')]
    public function test_every_staff_role_reaches_the_staff_dashboard(string $state): void
    {
        $this->actingAs(User::factory()->$state()->create())
            ->get('/admin')
            ->assertOk();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rolesThatDoNotManageAccounts(): array
    {
        return [
            'rental staff' => ['counterAgent'],
            'fleet manager' => ['fleetStaff'],
            'finance' => ['finance'],
            'branch manager' => ['branchManager'],
            'customer' => ['customer'],
        ];
    }

    #[DataProvider('rolesThatDoNotManageAccounts')]
    public function test_roles_other_than_system_administrator_cannot_manage_staff_accounts(string $state): void
    {
        $this->actingAs(User::factory()->$state()->create())
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_system_administrators_manage_staff_accounts(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/users')
            ->assertOk();
    }

    public function test_deactivated_users_are_locked_out_everywhere(): void
    {
        $staff = User::factory()->counterAgent()->create(['is_active' => false]);
        $customer = User::factory()->customer()->create(['is_active' => false]);

        $this->actingAs($staff)->get('/admin')->assertForbidden();
        $this->actingAs($customer)->get('/dashboard')->assertForbidden();
    }

    public function test_drivers_are_locked_out_of_the_admin_and_customer_areas(): void
    {
        // The trip-log portal (UC24) is not built, so a Driver has no
        // business in either existing area.
        $driver = User::factory()->driver()->create();

        $this->actingAs($driver)->get('/admin')->assertForbidden();
        $this->actingAs($driver)->get('/admin/vehicles')->assertForbidden();
        $this->actingAs($driver)->get('/dashboard')->assertForbidden();
        $this->actingAs($driver)->get('/reservations/create')->assertForbidden();
    }

    public function test_drivers_reach_account_tools_available_to_every_actor(): void
    {
        $driver = User::factory()->driver()->create();

        $this->actingAs($driver)->get('/notifications')->assertOk();
        $this->actingAs($driver)->get('/profile')->assertOk();
    }

    public function test_login_lands_a_driver_on_their_alerts(): void
    {
        $driver = User::factory()->driver()->create(['password' => 'password']);

        $this->post('/login', ['email' => $driver->email, 'password' => 'password'])
            ->assertRedirect(route('notifications.index'));
    }

    public function test_login_still_lands_staff_on_the_admin_dashboard(): void
    {
        $agent = User::factory()->counterAgent()->create(['password' => 'password']);

        $this->post('/login', ['email' => $agent->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_still_lands_customers_on_their_dashboard(): void
    {
        $customer = User::factory()->customer()->create(['password' => 'password']);

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect(route('customer.dashboard'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function customerAreaPages(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'reservations' => ['/reservations'],
            'rentals' => ['/rentals'],
            'profile' => ['/profile'],
        ];
    }

    #[DataProvider('customerAreaPages')]
    public function test_customer_area_pages_load_for_a_profiled_customer(string $path): void
    {
        $user = User::factory()->customer()->create();
        $user->customer()->create(['national_id' => '12345678']);

        $this->actingAs($user)->get($path)->assertOk();
    }
}
