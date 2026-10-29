<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The public contact form is validated to the same rigorous standard as the
 * rest of the app, then delivered through the contact facade — today an
 * in-app alert to the administrators.
 */
class ContactFormTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_enquiry_is_alerted_to_the_administrator(): void
    {
        $admin = User::factory()->admin()->create();

        $this->from(route('pages.contact'))
            ->post(route('pages.contact.store'), [
                'name' => 'Ali Hamisi',
                'email' => 'ali@example.com',
                'phone' => '0123456789',
                'subject' => 'Corporate hire',
                'message' => 'We need two SUVs with drivers for a week next month.',
            ])
            ->assertRedirect(route('pages.contact'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'contact',
        ]);
    }

    public function test_enquiry_rejects_junk_in_every_field(): void
    {
        $this->post(route('pages.contact.store'), [
            'name' => 'Ali775658',
            'email' => 'not-an-email',
            'phone' => 'afjdhgaj',
            'message' => 'short',
        ])->assertSessionHasErrors(['name', 'email', 'phone', 'message']);

        $this->assertDatabaseMissing('notifications', ['type' => 'contact']);
    }

    public function test_required_fields_are_enforced(): void
    {
        $this->post(route('pages.contact.store'), [
            'name' => '',
            'email' => '',
            'message' => '',
        ])->assertSessionHasErrors(['name', 'email', 'message']);
    }
}
