<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_standard_json_shape(): void
    {
        $this->getJson('/api/user')
            ->assertStatus(401)
            ->assertJson(['message' => 'You must be logged in to access this resource.']);
    }

    public function test_unauthenticated_request_without_a_json_accept_header_returns_401_not_500(): void
    {
        // هي بالضبط حالة الفرونت: fetch بدون هيدر Accept: application/json.
        // Authenticate middleware كان يستدعي route('login') المفقود => 500.
        $this->get('/api/user')
            ->assertStatus(401)
            ->assertJson(['message' => 'You must be logged in to access this resource.']);
    }

    public function test_unauthenticated_request_outside_api_returns_401_not_500(): void
    {
        // ما في route اسمه login بهالمشروع (Clerk بيتولى المصادقة)، فـ Laravel
        // كان يحاول يعمل redirect عليه ويرمي RouteNotFoundException => 500.
        Route::middleware('auth:clerk')->get('/_test/web-auth', fn () => 'ok');

        $this->get('/_test/web-auth')
            ->assertStatus(401)
            ->assertJson(['message' => 'You must be logged in to access this resource.']);
    }

    public function test_forbidden_request_returns_standard_json_shape(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer, 'clerk')
            ->getJson('/api/dashboard/stats')
            ->assertStatus(403)
            ->assertJson(['message' => 'You do not have permission to access this resource.']);
    }

    public function test_missing_model_returns_standard_json_shape(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'clerk')
            ->getJson('/api/products/999999')
            ->assertStatus(404)
            ->assertJson(['message' => 'The requested resource was not found.']);
    }

    public function test_validation_error_returns_standard_json_shape(): void
    {
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager, 'clerk')
            ->postJson('/api/products', [])
            ->assertStatus(422);

        $response->assertJsonStructure(['message', 'errors']);
    }

    public function test_unknown_route_returns_json_not_html(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'clerk')
            ->getJson('/api/this-route-does-not-exist')
            ->assertStatus(404)
            ->assertHeader('content-type', 'application/json');
    }
}
