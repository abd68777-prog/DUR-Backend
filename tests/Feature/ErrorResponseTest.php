<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_standard_json_shape(): void
    {
        $this->getJson('/api/user')
            ->assertStatus(401)
            ->assertJson(['message' => 'يجب تسجيل الدخول للوصول لهذا المورد']);
    }

    public function test_forbidden_request_returns_standard_json_shape(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer, 'clerk')
            ->getJson('/api/dashboard/stats')
            ->assertStatus(403)
            ->assertJson(['message' => 'ليس لديك صلاحية للوصول لهذا المورد']);
    }

    public function test_missing_model_returns_standard_json_shape(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'clerk')
            ->getJson('/api/products/999999')
            ->assertStatus(404)
            ->assertJson(['message' => 'العنصر المطلوب غير موجود']);
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
