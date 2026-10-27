<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_can_register_and_reaches_customer_dashboard(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test Customer',
            'email' => 'new@example.com',
            'phone' => '0711111111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('customer.dashboard'));

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->customer);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_weak_or_mismatched_passwords(): void
    {
        $this->from('/register')
            ->post('/register', [
                'name' => 'Weak',
                'email' => 'weak@example.com',
                'phone' => '0711111112',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');

        $this->from('/register')
            ->post('/register', [
                'name' => 'Mismatch',
                'email' => 'mismatch@example.com',
                'phone' => '0711111113',
                'password' => 'longenough1',
                'password_confirmation' => 'longenough2',
            ])
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_email_is_normalised_before_login(): void
    {
        $user = User::factory()->customer()->create([
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => '  USER@Example.com ',
                'password' => 'password123',
            ])
            ->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_are_redirected_to_the_admin_dashboard_on_login(): void
    {
        User::factory()->counterAgent()->create([
            'email' => 'staff@example.com',
            'password' => 'password123',
        ]);

        $this->from('/login')
            ->post('/login', ['email' => 'staff@example.com', 'password' => 'password123'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_bad_credentials_are_rejected(): void
    {
        User::factory()->customer()->create(['email' => 'user@example.com', 'password' => 'password123']);

        $this->from('/login')
            ->post('/login', ['email' => 'user@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_accounts_cannot_log_in(): void
    {
        User::factory()->customer()->create([
            'email' => 'banned@example.com',
            'password' => 'password123',
            'is_active' => false,
        ]);

        $this->from('/login')
            ->post('/login', ['email' => 'banned@example.com', 'password' => 'password123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
