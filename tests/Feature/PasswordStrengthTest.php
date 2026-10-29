<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Readme: one password strength policy everywhere (min 8, letters and
 * numbers), served through Password::defaults(). Previously each screen
 * drifted: register only asked for eight characters of anything.
 */
class PasswordStrengthTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_registration_rejects_a_number_only_password(): void
    {
        $this->from('/register')
            ->post('/register', [
                'name' => 'Numbers Only',
                'email' => 'num@example.com',
                'phone' => '0712222222',
                'password' => '12345678',
                'password_confirmation' => '12345678',
            ])
            ->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'num@example.com']);
    }

    public function test_registration_rejects_a_letter_only_password(): void
    {
        $this->from('/register')
            ->post('/register', [
                'name' => 'Letters Only',
                'email' => 'letters@example.com',
                'phone' => '0713333333',
                'password' => 'abcdefgh',
                'password_confirmation' => 'abcdefgh',
            ])
            ->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_registration_accepts_a_password_mixing_letters_and_numbers(): void
    {
        $this->from('/register')
            ->post('/register', [
                'name' => 'Mixed Case',
                'email' => 'mixed@example.com',
                'phone' => '0714444444',
                'password' => 'passw0rd',
                'password_confirmation' => 'passw0rd',
            ])
            ->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticated();
    }

    public function test_admin_creating_a_user_must_supply_a_strong_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Weakling',
            'email' => 'weakling@example.test',
            'role' => UserRole::CounterAgent->value,
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weakling@example.test']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Solid',
            'email' => 'solid@example.test',
            'role' => UserRole::CounterAgent->value,
            'password' => 'S0lidPass',
            'password_confirmation' => 'S0lidPass',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(
            Hash::check('S0lidPass', User::where('email', 'solid@example.test')->firstOrFail()->password)
        );
    }

    public function test_admin_replacing_someones_password_also_requires_strength(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->counterAgent()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->role->value,
            'password' => 'lettersonly',
            'password_confirmation' => 'lettersonly',
        ])->assertSessionHasErrors('password');

        // Leaving the fields blank keeps the current password untouched.
        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->role->value,
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasNoErrors();
    }

    public function test_profile_password_change_requires_letters_and_numbers(): void
    {
        $user = User::factory()->customer()->create(['password' => 'password123']);

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => 'password123',
            'password' => '11111111',
            'password_confirmation' => '11111111',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_password_reset_enforces_the_same_rule_as_everywhere_else(): void
    {
        $user = User::factory()->customer()->create();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ])->assertSessionHasErrors('password');

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'recover24',
            'password_confirmation' => 'recover24',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('recover24', $user->fresh()->password));
    }

    public function test_password_screens_render_the_strength_meter_component(): void
    {
        $admin = User::factory()->admin()->create();

        $this->from('/register')->get('/register')
            ->assertOk()
            ->assertSee('data-password-meter', false);

        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('data-password-meter', false);

        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-password-meter', false);
    }
}
