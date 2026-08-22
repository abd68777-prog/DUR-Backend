<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\ClerkUserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Svix\Exception\WebhookVerificationException;
use Svix\Webhook;

class ClerkWebhookController extends Controller
{
    #[OA\Post(
        path: '/webhooks/clerk',
        summary: 'Clerk webhook (not called by the frontend)',
        description: 'Internal endpoint called only by Clerk, protected by a Svix signature (svix-id/svix-timestamp/svix-signature headers). Handles the user.created event to create the user in the database.',
        tags: ['Webhooks'],
        responses: [
            new OA\Response(response: 200, description: 'Processed', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 400, description: 'Invalid signature', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.clerk.webhook_secret');
        $payload = $request->getContent();

        $headers = [
            'svix-id' => $request->header('svix-id'),
            'svix-timestamp' => $request->header('svix-timestamp'),
            'svix-signature' => $request->header('svix-signature'),
        ];

        try {
            $wh = new Webhook($secret);
            $verified = $wh->verify($payload, $headers);
        } catch (WebhookVerificationException $e) {
            Log::warning('Clerk webhook verification failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $eventData = is_string($verified) ? json_decode($verified, true) : (array) $verified;

        $eventType = $eventData['type'] ?? null;
        $data = $eventData['data'] ?? null;

        if ($eventType === 'user.created') {
            $this->handleUserCreated($data);
        }

        return response()->json(['message' => 'ok']);
    }

    private function handleUserCreated(array $data): void
    {
        $clerkId = $data['id'] ?? null;

        if (blank($clerkId)) {
            Log::warning('Clerk webhook user.created without an id', ['data' => $data]);

            return;
        }

        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';

        // نفس منطق تسجيل الدخول بالتوكن - بيتبنّى الصف الموجود بنفس الإيميل بدل
        // ما يكسر قيد users_email_unique. المصادقة عبر Clerk فما في كلمة سر.
        app(ClerkUserRepository::class)->resolve(
            clerkId: $clerkId,
            email: $data['email_addresses'][0]['email_address'] ?? null,
            name: trim("{$firstName} {$lastName}"),
        );

        Log::info('User created via Clerk webhook', ['clerk_id' => $clerkId]);
    }
}
