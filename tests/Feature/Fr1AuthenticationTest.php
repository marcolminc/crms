<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * FR1 — register, authenticate and manage roles and permissions.
 * Covers the two pillars the earlier suites missed: self-service password
 * recovery and the guarantee that a deactivated account is unusable even
 * while an existing session is still alive.
 */
class Fr1AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    // ----------------------------------------------------------------
    // Password recovery
    // ----------------------------------------------------------------

    public function test_guests_can_open_the_forgot_and_reset_pages(): void
    {
        $this->get('/forgot-password')->assertOk();
        $this->get('/reset-password/some-token')->assertOk()->assertSee('Choose a new password');
    }

    public function test_forgot_password_emails_a_reset_link_to_a_known_address(): void
    {
        Notification::fake();

        $user = User::factory()->customer()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_rejects_an_unknown_address(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_a_valid_token_lets_the_user_set_a_new_password(): void
    {
        $user = User::factory()->customer()->create(['password' => 'OldPass123']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'BrandNewPass1',
            'password_confirmation' => 'BrandNewPass1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('BrandNewPass1', $user->fresh()->password));
        $this->post('/login', ['email' => $user->email, 'password' => 'OldPass123'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'BrandNewPass1'])->assertRedirect();
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $user = User::factory()->customer()->create();

        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'BrandNewPass1',
            'password_confirmation' => 'BrandNewPass1',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_password_enforces_strength_rules(): void
    {
        $user = User::factory()->customer()->create();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    // ----------------------------------------------------------------
    // Deactivated accounts are unusable everywhere
    // ----------------------------------------------------------------

    public function test_a_deactivated_customer_with_a_live_session_is_cut_off(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get('/dashboard')->assertOk();

        $customer->forceFill(['is_active' => false])->save();

        $this->actingAs($customer)->get('/dashboard')->assertForbidden();
        $this->actingAs($customer)->get('/profile')->assertForbidden();
        $this->actingAs($customer)->get('/notifications')->assertForbidden();
    }

    public function test_a_deactivated_staff_member_is_cut_off_from_the_admin_area(): void
    {
        $staff = User::factory()->counterAgent()->create();
        $staff->forceFill(['is_active' => false])->save();

        $this->actingAs($staff)->get('/admin')->assertForbidden();
    }

    public function test_a_deactivated_user_cannot_log_back_in(): void
    {
        $user = User::factory()->customer()->create(['password' => 'Password123']);
        $user->forceFill(['is_active' => false])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'Password123'])
            ->assertSessionHasErrors('email');
    }

    // ----------------------------------------------------------------
    // Admin scope in the navigation (design doc §2)
    // ----------------------------------------------------------------

    public function test_admins_see_a_dedicated_administration_group(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Administration')
            ->assertSee('Users & roles');
    }

    public function test_non_admin_staff_do_not_see_the_administration_group(): void
    {
        $staff = User::factory()->counterAgent()->create();

        $this->actingAs($staff)->get('/admin')
            ->assertOk()
            ->assertDontSee('Administration');
    }
}
