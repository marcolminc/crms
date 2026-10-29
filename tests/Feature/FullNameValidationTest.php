<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Readme: a full name is a name, not a username. Digits and stray symbols are
 * refused ("5672894", "Ali Halisi775658", "bob_the_builder"), while ordinary
 * names with spaces, hyphens, apostrophes and periods still pass.
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

    public function test_signup_rejects_a_name_with_embedded_numbers(): void
    {
        $this->from('/register')
            ->post('/register', [
                'name' => 'Ali Halisi775658',
                'email' => 'embedded@example.com',
                'phone' => '0717777777',
                'password' => 'passw0rd',
                'password_confirmation' => 'passw0rd',
            ])
            ->assertSessionHasErrors('name');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'embedded@example.com']);
    }

    public function test_ordinary_names_with_real_separators_are_accepted(): void
    {
        $user = User::factory()->customer()->create();

        foreach (["Mary O'Connell", 'Jean-Pierre Habibu', 'Dr. Ali Hamisi', 'Zoë Amina'] as $good) {
            $this->actingAs($user)->put(route('profile.update'), [
                'name' => $good,
                'email' => $user->email,
            ])->assertSessionHasNoErrors("name {$good} should be accepted");
        }
    }

    public function test_names_with_symbols_or_underscores_are_rejected(): void
    {
        $user = User::factory()->customer()->create();

        foreach (['bob_the_builder', 'Ali <b>', 'name#1', 'A', 'Mariam 24-30'] as $bad) {
            $this->actingAs($user)->put(route('profile.update'), [
                'name' => $bad,
                'email' => $user->email,
            ])->assertSessionHasErrors('name', "name {$bad} should be rejected");
        }
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
        // Assert an apostrophe-free fragment; the full pattern contains a '
        // which the component attribute bag encodes as &#039; in the raw HTML.
        $expected = 'pattern="[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ';

        $this->from('/register')->get('/register')
            ->assertOk()
            ->assertSee($expected, false);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee($expected, false);

        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee($expected, false);
    }
}
