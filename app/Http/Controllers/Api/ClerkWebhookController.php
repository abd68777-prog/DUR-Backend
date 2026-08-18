<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        summary: 'Clerk webhook (لسه ما بيلمسه الفرونت-إند)',
        description: 'مسار داخلي بينادى من Clerk فقط، محمي بتوقيع Svix (svix-id/svix-timestamp/svix-signature headers). بيعالج حدث user.created لإنشاء المستخدم بقاعدة البيانات.',
        tags: ['Webhooks'],
        responses: [
            new OA\Response(response: 200, description: 'تمت المعالجة', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 400, description: 'توقيع غير صحيح', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
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
        $email = $data['email_addresses'][0]['email_address'] ?? null;
        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';

        User::firstOrCreate(
            ['clerk_id' => $clerkId],
            [
                'name' => trim("{$firstName} {$lastName}") ?: 'مستخدم Clerk',
                'email' => $email ?? "{$clerkId}@placeholder.clerk",
                'password' => bcrypt(str()->random(32)),
                'role' => 'customer',
            ]
        );

        Log::info('User created via Clerk webhook', ['clerk_id' => $clerkId]);
    }
}
