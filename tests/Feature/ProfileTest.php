<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_customer_updates_login_details_and_profile_fields_together(): void
    {
        $user = User::factory()->customer()->create(['email' => 'me@example.com']);
        Customer::factory()->create(['user_id' => $user->id, 'city' => 'Mwanza']);

        $this->actingAs($user)->put('/profile', [
            'name' => 'Elias Seka',
            'email' => 'me@example.com',
            'phone' => '0788000000',
            'national_id' => '99887766',
            'driving_license_no' => 'DL-999',
            'city' => 'Geita',
            'address' => 'Plot 12, Nyanzaru',
            'emergency_contact_name' => 'Jane',
            'emergency_contact_phone' => '0688000000',
        ])->assertRedirect(route('profile.edit'))->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('Elias Seka', $user->name);
        $this->assertSame('0788000000', $user->phone);
        $this->assertSame('99887766', $user->customer->national_id);
        $this->assertSame('Geita', $user->customer->city);
    }

    public function test_validation_rejects_a_taken_email_and_a_future_birth_date(): void
    {
        User::factory()->customer()->create(['email' => 'taken@example.com']);
        $user = User::factory()->customer()->create();
        Customer::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->put('/profile', ['name' => 'X', 'email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');

        $this->actingAs($user)
            ->put('/profile', [
                'name' => 'X',
                'email' => 'free@example.com',
                'date_of_birth' => now()->addYear()->toDateString(),
            ])
            ->assertSessionHasErrors('date_of_birth');
    }

    public function test_staff_profiles_never_gain_a_customer_record(): void
    {
        $staff = User::factory()->counterAgent()->create();

        $this->actingAs($staff)
            ->put('/profile', [
                'name' => 'Staff Member',
                'email' => $staff->email,
                'phone' => '0712345678',
                'city' => 'Mwanza',
            ])
            ->assertSessionHas('status');

        $this->assertNull($staff->fresh()->customer);
        $this->assertSame('Staff Member', $staff->fresh()->name);
    }

    public function test_a_user_can_change_their_password_from_the_profile_page(): void
    {
        $user = User::factory()->customer()->create(['password' => 'password123']);

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'wrong-once',
                'password' => 'brandnewpass1',
                'password_confirmation' => 'brandnewpass1',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'password123',
                'password' => 'brandnewpass1',
                'password_confirmation' => 'brandnewpass1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brandnewpass1', $user->fresh()->password));
    }
}
