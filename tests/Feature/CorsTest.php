<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Two origins forces the CORS library's dynamic per-request origin check
        // (a single configured origin is always echoed back as a "safe+cacheable" optimization).
        Config::set('cors.allowed_origins', ['http://localhost:5173', 'https://dur.example.com']);
    }

    public function test_allowed_origin_receives_cors_header(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5173'])
            ->getJson('/api/user')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_preflight_request_from_allowed_origin_is_accepted(): void
    {
        $response = $this->call('OPTIONS', '/api/products', [], [], [], [
            'HTTP_Origin' => 'http://localhost:5173',
            'HTTP_Access-Control-Request-Method' => 'GET',
        ]);

        $response->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_disallowed_origin_does_not_receive_cors_header(): void
    {
        $this->withHeaders(['Origin' => 'https://evil-site.com'])
            ->getJson('/api/user')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}