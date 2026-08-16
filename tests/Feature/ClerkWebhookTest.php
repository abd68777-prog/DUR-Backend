<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Svix\Webhook;
use Tests\TestCase;

class ClerkWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.clerk.webhook_secret', self::SECRET);
    }

    private function signedHeaders(string $payload, ?string $msgId = null, ?int $timestamp = null): array
    {
        $msgId ??= 'msg_'.Str::random(10);
        $timestamp ??= time();

        $webhook = new Webhook(self::SECRET);
        $signature = $webhook->sign($msgId, $timestamp, $payload);

        return [
            'svix-id' => $msgId,
            'svix-timestamp' => (string) $timestamp,
            'svix-signature' => $signature,
        ];
    }

    private function userCreatedPayload(string $clerkId, string $email): string
    {
        return json_encode([
            'type' => 'user.created',
            'data' => [
                'id' => $clerkId,
                'email_addresses' => [
                    ['email_address' => $email],
                ],
                'first_name' => 'محمد',
                'last_name' => 'أحمد',
            ],
        ]);
    }

    public function test_webhook_rejects_request_without_signature_headers(): void
    {
        $this->postJson('/api/webhooks/clerk', ['type' => 'user.created'])
            ->assertStatus(400);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = $this->userCreatedPayload('user_123', 'test@example.com');

        $this->call(
            'POST',
            '/api/webhooks/clerk',
            server: [
                'HTTP_svix-id' => 'msg_123',
                'HTTP_svix-timestamp' => (string) time(),
                'HTTP_svix-signature' => 'v1,invalidsignature==',
            ],
            content: $payload
        )->assertStatus(400);

        $this->assertDatabaseMissing('users', ['clerk_id' => 'user_123']);
    }

    public function test_webhook_creates_user_on_user_created_event(): void
    {
        $payload = $this->userCreatedPayload('user_123', 'newuser@example.com');
        $headers = $this->signedHeaders($payload);

        $this->call(
            'POST',
            '/api/webhooks/clerk',
            server: $this->toServerHeaders($headers),
            content: $payload
        )->assertOk();

        $this->assertDatabaseHas('users', [
            'clerk_id' => 'user_123',
            'email' => 'newuser@example.com',
            'name' => 'محمد أحمد',
            'role' => 'customer',
        ]);
    }

    public function test_webhook_does_not_duplicate_existing_user(): void
    {
        User::factory()->create(['clerk_id' => 'user_123']);

        $payload = $this->userCreatedPayload('user_123', 'newuser@example.com');
        $headers = $this->signedHeaders($payload);

        $this->call(
            'POST',
            '/api/webhooks/clerk',
            server: $this->toServerHeaders($headers),
            content: $payload
        )->assertOk();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_webhook_ignores_unhandled_event_types(): void
    {
        $payload = json_encode(['type' => 'user.updated', 'data' => ['id' => 'user_999']]);
        $headers = $this->signedHeaders($payload);

        $this->call(
            'POST',
            '/api/webhooks/clerk',
            server: $this->toServerHeaders($headers),
            content: $payload
        )->assertOk();

        $this->assertDatabaseMissing('users', ['clerk_id' => 'user_999']);
    }

    private function toServerHeaders(array $headers): array
    {
        $server = [];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.$name] = $value;
        }

        return $server;
    }
}