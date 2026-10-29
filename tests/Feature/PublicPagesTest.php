<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The public company pages (About, Contact, Terms) must be reachable whether or
 * not a visitor is signed in, and the navigation that surfaces them (a Company
 * dropdown for guests, a Company menu group + breadcrumb for signed-in roles)
 * must be present on every screen.
 */
class PublicPagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_can_open_every_company_page(): void
    {
        foreach (['pages.about', 'pages.contact', 'pages.terms'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Land Valley', false);
        }
    }

    public function test_guest_header_offers_the_company_dropdown(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Company')
            ->assertSee('About us')
            ->assertSee('Contact us')
            ->assertSee('Terms');
    }

    public function test_page_content_is_rendered(): void
    {
        $this->get(route('pages.about'))->assertOk()->assertSee('Who we are');
        $this->get(route('pages.terms'))->assertOk()->assertSee('Eligibility to hire');
        $this->get(route('pages.contact'))
            ->assertOk()
            ->assertSee('Send us a message')
            ->assertSee('pattern="0[0-9]{9}"', false)
            ->assertSee('pattern="[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ', false);
    }

    public function test_signed_in_users_get_the_company_group_and_breadcrumb(): void
    {
        $staff = User::factory()->counterAgent()->create();

        $this->actingAs($staff)->get(route('pages.about'))
            ->assertOk()
            // The role menu carries the Company group and the breadcrumb strip
            // reflects the active page for a signed-in user.
            ->assertSee('Company')
            ->assertSee('About us')
            ->assertSee('Menu');

        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get(route('pages.terms'))
            ->assertOk()
            ->assertSee('Company')
            ->assertSee('Terms & conditions');
    }
}
