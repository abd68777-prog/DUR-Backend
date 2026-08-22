<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Lcobucci\JWT\Token;
use RonasIT\Clerk\Contracts\UserRepositoryContract;

class ClerkUserRepository implements UserRepositoryContract
{
    public function fromToken(Token $token): Authenticatable
    {
        $firstName = $token->claims()->get('first_name');
        $lastName = $token->claims()->get('last_name');

        return $this->resolve(
            clerkId: $token->claims()->get('sub'),
            email: $token->claims()->get('email'),
            name: trim("{$firstName} {$lastName}"),
        );
    }

    /**
     * بيرجّع المستخدم المطابق لـ clerk_id، وإذا مش موجود بيتبنّى الصف يلي عنده
     * نفس الإيميل بدل ما يعمل INSERT جديد.
     *
     * ليش: لما ينحذف حساب من Clerk وينعاد إنشاؤه، بيجي بـ clerk_id جديد ونفس
     * الإيميل. البحث بالـ clerk_id لحاله ما بيلاقي شي، فالـ INSERT كان بيكسر
     * قيد users_email_unique ويرجع 500 على كل طلب مصادَق.
     */
    public function resolve(string $clerkId, ?string $email, ?string $name): User
    {
        // توكن Clerk الافتراضي ما بيحمل الإيميل، فمنحتاج بديل ثابت لكل clerk_id.
        $email = filled($email) ? $email : "{$clerkId}@placeholder.clerk";
        $name = filled(trim((string) $name)) ? trim((string) $name) : 'Clerk User';

        if ($user = User::where('clerk_id', $clerkId)->first()) {
            return $user;
        }

        if ($user = User::where('email', $email)->first()) {
            // منحدّث الـ clerk_id بس منترك الـ role متل ما هو، حتى الأدمن ما
            // يفقد صلاحيته لمجرد إنه أعاد إنشاء حسابه بـ Clerk.
            $user->update(['clerk_id' => $clerkId, 'name' => $name]);

            return $user;
        }

        try {
            return User::create([
                'clerk_id' => $clerkId,
                'name' => $name,
                'email' => $email,
                'role' => 'customer',
            ]);
        } catch (QueryException) {
            // طلبين متزامنين وصلوا لهون سوا - التاني بيلاقي صف الأول.
            return User::where('clerk_id', $clerkId)->orWhere('email', $email)->firstOrFail();
        }
    }
}
