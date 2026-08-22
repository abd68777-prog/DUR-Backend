<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Lcobucci\JWT\Token;
use RonasIT\Clerk\Contracts\UserRepositoryContract;

class ClerkUserRepository implements UserRepositoryContract
{
    public function fromToken(Token $token): Authenticatable
    {
        $clerkId = $token->claims()->get('sub');

        $email = $token->claims()->get('email');
        $firstName = $token->claims()->get('first_name');
        $lastName = $token->claims()->get('last_name');

        return User::firstOrCreate(
            ['clerk_id' => $clerkId],
            [
                'name' => trim("{$firstName} {$lastName}") ?: 'Clerk User',
                'email' => $email ?? "{$clerkId}@placeholder.clerk",
                // المصادقة عبر Clerk، فما منحتاج كلمة سر. العمود صار nullable
                // (migration: make_password_nullable_on_users_table).
                // 'password' => bcrypt(str()->random(32)),
                'role' => 'customer',
            ]
        );
    }
}
