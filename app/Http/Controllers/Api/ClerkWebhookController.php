<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        description: 'Internal endpoint called only by Clerk, protected by a Svix signature (svix-id/svix-timestamp/svix-signature headers). Handles user.created, user.updated and user.deleted to keep the local users table in sync with Clerk. All three events must be enabled in the Clerk Dashboard.',
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
        $data = $eventData['data'] ?? [];

        if (! is_array($data)) {
            $data = (array) $data;
        }

        match ($eventType) {
            'user.created' => $this->handleUserCreated($data),
            'user.updated' => $this->handleUserUpdated($data),
            'user.deleted' => $this->handleUserDeleted($data),
            default => null,
        };

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

    /**
     * تغيير الإيميل أو الاسم بـ Clerk لازم ينعكس عنا، وإلا `user:set-role`
     * بالإيميل الجديد ما بيلاقي المستخدم.
     */
    private function handleUserUpdated(array $data): void
    {
        $clerkId = $data['id'] ?? null;

        if (blank($clerkId)) {
            Log::warning('Clerk webhook user.updated without an id', ['data' => $data]);

            return;
        }

        $user = User::where('clerk_id', $clerkId)->first();

        if (! $user) {
            // ما منعرفه - منعامله كإنشاء جديد بدل ما نتجاهل الحدث.
            $this->handleUserCreated($data);

            return;
        }

        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';
        $email = $data['email_addresses'][0]['email_address'] ?? null;

        // منتجاهل التحديث إذا الإيميل الجديد محجوز لمستخدم تاني، بدل ما نكسر
        // قيد users_email_unique ونرجّع 500 لـ Clerk (وبيعيد المحاولة للأبد).
        if (filled($email) && User::where('email', $email)->whereKeyNot($user->id)->exists()) {
            Log::warning('Clerk webhook user.updated email already taken', [
                'clerk_id' => $clerkId,
            ]);

            $email = null;
        }

        $user->update(array_filter([
            'name' => trim("{$firstName} {$lastName}") ?: null,
            'email' => $email,
        ]));

        Log::info('User updated via Clerk webhook', ['clerk_id' => $clerkId]);
    }

    /**
     * بدون هالمعالجة بيضل صف المستخدم بالـ DB بعد حذفه من Clerk. وبيصير مشكلة
     * فعلية لما ينعمل حساب جديد بنفس الإيميل: بيجي بـ clerk_id جديد فما بينلاقى،
     * والإدخال بيكسر قيد users_email_unique.
     */
    private function handleUserDeleted(array $data): void
    {
        $clerkId = $data['id'] ?? null;

        if (blank($clerkId)) {
            Log::warning('Clerk webhook user.deleted without an id', ['data' => $data]);

            return;
        }

        $deleted = User::where('clerk_id', $clerkId)->delete();

        Log::info('User deleted via Clerk webhook', [
            'clerk_id' => $clerkId,
            'deleted' => $deleted,
        ]);
    }
}
