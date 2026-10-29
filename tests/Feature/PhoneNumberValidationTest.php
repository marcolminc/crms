<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Readme: a phone number is a real number, not a word. App\Rules\PhoneNumber
 * wants ten digits beginning with 0 (the +255 country code and ordinary
 * separators are tolerated, then resolved to the local form); "afjdhgaj" and
 * short/wrong-prefix values are refused.
 */
class PhoneNumberValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_signup_rejects_a_phone_of_letters(): void
    {
        $this->from('/register')
            ->post('/register', [
                'name' => 'Ali Hamisi',
                'email' => 'phoneletters@example.com',
                'phone' => 'afjdhgaj',
                'password' => 'passw0rd',
                'password_confirmation' => 'passw0rd',
            ])
            ->assertSessionHasErrors('phone');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'phoneletters@example.com']);
    }

    public function test_signup_rejects_a_phone_that_is_not_ten_digits_beginning_with_zero(): void
    {
        // too short, too long, and one that starts with 1 instead of 0.
        foreach (['012345678', '01234567890', '1234567890'] as $i => $bad) {
            $this->from('/register')
                ->post('/register', [
                    'name' => 'Ali Hamisi',
                    'email' => "bad{$i}@example.com",
                    'phone' => $bad,
                    'password' => 'passw0rd',
                    'password_confirmation' => 'passw0rd',
                ])
                ->assertSessionHasErrors('phone', "phone {$bad} should be rejected");

            $this->assertDatabaseMissing('users', ['email' => "bad{$i}@example.com"]);
        }
    }

    public function test_signup_accepts_the_canonical_and_formatted_numbers(): void
    {
        $formats = [
            '0123456789',
            '0712345678',
            '+255 712 345 678',
            '+255712345678',
            '0712-345-678',
            '(0712) 345 678',
        ];

        foreach ($formats as $i => $phone) {
            $this->from('/register')
                ->post('/register', [
                    'name' => 'Ali Hamisi',
                    'email' => "format{$i}@example.com",
                    'phone' => $phone,
                    'password' => 'passw0rd',
                    'password_confirmation' => 'passw0rd',
                ])
                ->assertSessionHasNoErrors("phone for {$phone}");
        }
    }

    public function test_profile_update_rejects_an_all_letters_phone(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => 'abcdefgh',
        ])->assertSessionHasErrors('phone');

        // An optional phone left blank is still allowed.
        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '',
        ])->assertSessionHasNoErrors();
    }

    public function test_admin_creating_a_user_rejects_a_bad_phone(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Counter Clerk',
            'email' => 'badphone@example.test',
            'phone' => 'callme',
            'role' => UserRole::CounterAgent->value,
            'password' => 'S0lidPass',
            'password_confirmation' => 'S0lidPass',
        ])->assertSessionHasErrors('phone');

        $this->assertDatabaseMissing('users', ['email' => 'badphone@example.test']);
    }

    public function test_customer_emergency_contact_phone_is_validated(): void
    {
        $staff = User::factory()->counterAgent()->create();

        $this->actingAs($staff)->post(route('admin.customers.store'), [
            'national_id' => 'NID-1',
            'emergency_contact_name' => 'Jane',
            'emergency_contact_phone' => 'notaphone',
        ])->assertSessionHasErrors('emergency_contact_phone');

        $this->actingAs($staff)->post(route('admin.customers.store'), [
            'national_id' => 'NID-2',
            'emergency_contact_name' => 'Jane',
            'emergency_contact_phone' => '0123456789',
        ])->assertSessionHasNoErrors();
    }

    public function test_phone_screens_carry_the_client_pattern(): void
    {
        $this->from('/register')->get('/register')
            ->assertOk()
            ->assertSee('pattern="0[0-9]{9}"', false);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('pattern="0[0-9]{9}"', false);

        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('pattern="0[0-9]{9}"', false);
    }
}
