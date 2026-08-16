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
                'name' => trim("{$firstName} {$lastName}") ?: 'مستخدم Clerk',
                'email' => $email ?? "{$clerkId}@placeholder.clerk",
                'password' => bcrypt(str()->random(32)), // لو عمود password عندك NOT NULL
                'role' => 'customer',
            ]
        );
    }
}
