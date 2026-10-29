<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Readme: robust validation front-end and back. App\Rules\Identifier treats a
 * national ID / passport / driving-licence number as a reference code — letters
 * and digits, optionally separated by spaces, / or - — so junk like "----" or
 * "<x>" is refused while the many real-world shapes still pass. The same rule's
 * pattern is mirrored on the client inputs so the field flags as the reader types.
 */
class IdentifierValidationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_profile_update_rejects_a_junky_identifier(): void
    {
        $user = User::factory()->customer()->create();

        foreach (['----', '<x>', 'id#1', '///', '???'] as $i => $bad) {
            $this->actingAs($user)->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'national_id' => $bad,
            ])->assertSessionHasErrors('national_id', "national_id {$bad} should be rejected");
        }
    }

    public function test_profile_update_accepts_the_common_identifier_shapes(): void
    {
        $user = User::factory()->customer()->create();

        foreach (['12345678', 'A1234567', 'DL-1234567', 'NID-1', 'AB 1234', 'X/Y/123'] as $id) {
            $this->actingAs($user)->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'passport_no' => $id,
            ])->assertSessionHasNoErrors("passport_no for {$id}");
        }

        // Blank optional identifiers are still allowed.
        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'driving_license_no' => '',
        ])->assertSessionHasNoErrors();
    }

    public function test_customer_store_validates_identifiers_and_emergency_name(): void
    {
        $staff = User::factory()->counterAgent()->create();

        $this->actingAs($staff)->post(route('admin.customers.store'), [
            'national_id' => '<bad>',
            'passport_no' => 'A1234567',
            'driving_license_no' => 'DL-9999999',
            'emergency_contact_name' => 'Jane',
        ])->assertSessionHasErrors('national_id');

        $this->actingAs($staff)->post(route('admin.customers.store'), [
            'national_id' => '12345678',
            'passport_no' => 'A1234567',
            'driving_license_no' => 'DL-9999999',
            'emergency_contact_name' => '5672894',
        ])->assertSessionHasErrors('emergency_contact_name');

        $this->actingAs($staff)->post(route('admin.customers.store'), [
            'national_id' => '12345678',
            'passport_no' => 'A1234567',
            'driving_license_no' => 'DL-9999999',
            'emergency_contact_name' => 'Ali Hamisi',
        ])->assertSessionHasNoErrors();
    }

    public function test_identifier_and_name_screens_carry_the_client_pattern(): void
    {
        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('pattern="[A-Za-z0-9]+([ /\-][A-Za-z0-9]+)*"', false)
            ->assertSee('pattern="[A-Za-z]{2,}.*"', false);

        $staff = User::factory()->counterAgent()->create();
        $this->actingAs($staff)->get(route('admin.customers.create'))
            ->assertOk()
            ->assertSee('pattern="[A-Za-z0-9]+([ /\-][A-Za-z0-9]+)*"', false)
            ->assertSee('pattern="[A-Za-z]{2,}.*"', false);
    }
}
