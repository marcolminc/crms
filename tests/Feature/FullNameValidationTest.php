<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Readme: a full name is a name, not a number. "5672894" used to sail
 * through signup because the field only checked required/string/max.
 */
class FullNameValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_signup_rejects_a_name_that_is_all_numbers(): void
    {
        $this->from('/register')
            ->post('/register', [
                'name' => '5672894',
                'email' => 'digits@example.com',
                'phone' => '0715555555',
                'password' => 'passw0rd',
                'password_confirmation' => 'passw0rd',
            ])
            ->assertSessionHasErrors('name');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'digits@example.com']);
    }

    public function test_signup_rejects_a_name_of_only_punctuation(): void
    {
        $this->from('/register')
            ->post('/register', [
                'name' => '-----',
                'email' => 'punct@example.com',
                'phone' => '0716666666',
                'password' => 'passw0rd',
                'password_confirmation' => 'passw0rd',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_signup_still_accepts_names_that_contain_numbers_or_hyphens(): void
    {
        $this->from('/register')
            ->post('/register', [
                'name' => 'Mariam 24-30 Hemisi',
                'email' => 'mixedname@example.com',
                'phone' => '0717777777',
                'password' => 'passw0rd',
                'password_confirmation' => 'passw0rd',
            ])
            ->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticated();
    }

    public function test_profile_update_rejects_an_all_number_name(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => '98765',
            'email' => $user->email,
        ])->assertSessionHasErrors('name');

        $this->assertSame($user->name, $user->fresh()->name);
    }

    public function test_admin_creating_a_user_rejects_an_all_number_name(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => '12345678',
            'email' => 'numericname@example.test',
            'role' => UserRole::CounterAgent->value,
            'password' => 'S0lidPass',
            'password_confirmation' => 'S0lidPass',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('users', ['email' => 'numericname@example.test']);
    }

    public function test_admin_editing_a_user_rejects_an_all_number_name(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->counterAgent()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => '000',
            'email' => $target->email,
            'role' => $target->role->value,
        ])->assertSessionHasErrors('name');
    }

    public function test_name_screens_carry_the_client_pattern(): void
    {
        $this->from('/register')->get('/register')
            ->assertOk()
            ->assertSee('pattern="[A-Za-z]{2,}.*"', false);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('pattern="[A-Za-z]{2,}.*"', false);

        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('pattern="[A-Za-z]{2,}.*"', false);
    }
}
