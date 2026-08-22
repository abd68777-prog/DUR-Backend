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

    public function test_webhook_creates_user_without_a_password(): void
    {
        // المصادقة عبر Clerk، فعمود password صار nullable وما منعبّيه.
        $payload = $this->userCreatedPayload('user_456', 'nopassword@example.com');
        $headers = $this->signedHeaders($payload);

        $this->call(
            'POST',
            '/api/webhooks/clerk',
            server: $this->toServerHeaders($headers),
            content: $payload
        )->assertOk();

        $this->assertNull(User::where('clerk_id', 'user_456')->sole()->password);
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

    public function test_webhook_adopts_an_existing_user_with_the_same_email(): void
    {
        // نفس الإيميل بـ clerk_id قديم - لازم يربط الحساب مش يرمي خطأ unique.
        $existing = User::factory()->create([
            'clerk_id' => 'user_old',
            'email' => 'newuser@example.com',
        ]);

        $payload = $this->userCreatedPayload('user_123', 'newuser@example.com');
        $headers = $this->signedHeaders($payload);

        $this->call(
            'POST',
            '/api/webhooks/clerk',
            server: $this->toServerHeaders($headers),
            content: $payload
        )->assertOk();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('user_123', $existing->fresh()->clerk_id);
    }

    public function test_webhook_deletes_the_user_on_user_deleted_event(): void
    {
        User::factory()->create(['clerk_id' => 'user_123']);

        $payload = json_encode(['type' => 'user.deleted', 'data' => ['id' => 'user_123']]);
        $headers = $this->signedHeaders($payload);

        $this->call(
            'POST',
            '/api/webhooks/clerk',
            server: $this->toServerHeaders($headers),
            content: $payload
        )->assertOk();

        $this->assertDatabaseMissing('users', ['clerk_id' => 'user_123']);
    }

    public function test_webhook_deleting_an_unknown_user_is_a_no_op(): void
    {
        User::factory()->create(['clerk_id' => 'user_keep']);

        $payload = json_encode(['type' => 'user.deleted', 'data' => ['id' => 'user_gone']]);
        $headers = $this->signedHeaders($payload);

        $this->call(
            'POST',
            '/api/webhooks/clerk',
            server: $this->toServerHeaders($headers),
            content: $payload
        )->assertOk();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_webhook_frees_the_email_so_the_account_can_be_recreated(): void
    {
        // السيناريو الكامل: إنشاء -> حذف من Clerk -> إنشاء من جديد بنفس الإيميل.
        $created = $this->userCreatedPayload('user_first', 'same@example.com');
        $this->postSigned($created)->assertOk();

        $deleted = json_encode(['type' => 'user.deleted', 'data' => ['id' => 'user_first']]);
        $this->postSigned($deleted)->assertOk();

        $recreated = $this->userCreatedPayload('user_second', 'same@example.com');
        $this->postSigned($recreated)->assertOk();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'clerk_id' => 'user_second',
            'email' => 'same@example.com',
        ]);
    }

    public function test_webhook_updates_name_and_email_on_user_updated_event(): void
    {
        User::factory()->create([
            'clerk_id' => 'user_123',
            'email' => 'old@example.com',
            'name' => 'Old Name',
        ]);

        $payload = json_encode([
            'type' => 'user.updated',
            'data' => [
                'id' => 'user_123',
                'email_addresses' => [['email_address' => 'new@example.com']],
                'first_name' => 'New',
                'last_name' => 'Name',
            ],
        ]);

        $this->postSigned($payload)->assertOk();

        $this->assertDatabaseHas('users', [
            'clerk_id' => 'user_123',
            'email' => 'new@example.com',
            'name' => 'New Name',
        ]);
    }

    public function test_webhook_update_does_not_steal_an_email_owned_by_another_user(): void
    {
        User::factory()->create(['clerk_id' => 'user_a', 'email' => 'taken@example.com']);
        User::factory()->create(['clerk_id' => 'user_b', 'email' => 'own@example.com']);

        $payload = json_encode([
            'type' => 'user.updated',
            'data' => [
                'id' => 'user_b',
                'email_addresses' => [['email_address' => 'taken@example.com']],
                'first_name' => 'Renamed',
                'last_name' => '',
            ],
        ]);

        $this->postSigned($payload)->assertOk();

        // الاسم بينتحدّث، الإيميل لأ - وما في خطأ unique.
        $this->assertDatabaseHas('users', [
            'clerk_id' => 'user_b',
            'email' => 'own@example.com',
            'name' => 'Renamed',
        ]);
    }

    public function test_webhook_ignores_unhandled_event_types(): void
    {
        // منعالج بس أحداث user.* - أي شي تاني بيرجع ok بدون ما يلمس الـ DB.
        $payload = json_encode(['type' => 'session.created', 'data' => ['id' => 'sess_999']]);

        $this->postSigned($payload)->assertOk();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_webhook_creates_the_user_when_an_update_arrives_first(): void
    {
        // إذا فات علينا حدث user.created (مثلاً الـ webhook انضاف متأخر)، حدث
        // التحديث لازم يزامن المستخدم بدل ما يتجاهله.
        $payload = json_encode([
            'type' => 'user.updated',
            'data' => [
                'id' => 'user_999',
                'email_addresses' => [['email_address' => 'late@example.com']],
                'first_name' => 'Late',
                'last_name' => 'User',
            ],
        ]);

        $this->postSigned($payload)->assertOk();

        $this->assertDatabaseHas('users', [
            'clerk_id' => 'user_999',
            'email' => 'late@example.com',
            'role' => 'customer',
        ]);
    }

    private function postSigned(string $payload): \Illuminate\Testing\TestResponse
    {
        return $this->call(
            'POST',
            '/api/webhooks/clerk',
            server: $this->toServerHeaders($this->signedHeaders($payload)),
            content: $payload
        );
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
