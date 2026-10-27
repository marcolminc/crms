<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The site runs behind nginx and, during sandbox testing, behind an ngrok
 * tunnel that terminates TLS and forwards X-Forwarded-Proto. If Laravel does
 * not trust those headers it emits http:// asset URLs and browsers block
 * them as mixed content on the https page (FR: deployment correctness).
 */
class ProxiedRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_forwarded_headers_rewrite_the_public_scheme_and_host(): void
    {
        // Asserts on route()-generated navigation links: the base TestCase runs
        // withoutVite(), so @vite asset tags are stripped from test responses.
        $response = $this->withServerVariables([
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'unheard-humble-vegan.ngrok-free.dev',
        ])->get('/');

        $response->assertOk();
        $this->assertStringContainsString(
            'https://unheard-humble-vegan.ngrok-free.dev/login',
            $response->getContent(),
        );
    }

    public function test_plain_requests_still_generate_local_urls(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertStringContainsString('http://localhost/login', $response->getContent());
        $this->assertStringNotContainsString('https://localhost/login', $response->getContent());
    }
}
