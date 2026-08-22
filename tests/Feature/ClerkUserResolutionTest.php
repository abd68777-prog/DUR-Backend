<?php

namespace Tests\Feature;

use App\Models\User;
use App\Repositories\ClerkUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClerkUserResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): ClerkUserRepository
    {
        return app(ClerkUserRepository::class);
    }

    public function test_it_returns_the_existing_user_matching_the_clerk_id(): void
    {
        $existing = User::factory()->create(['clerk_id' => 'user_abc']);

        $resolved = $this->repository()->resolve('user_abc', $existing->email, 'Someone Else');

        $this->assertSame($existing->id, $resolved->id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_creates_a_new_user_when_nothing_matches(): void
    {
        $user = $this->repository()->resolve('user_new', 'new@example.com', 'New User');

        $this->assertSame('customer', $user->role);
        $this->assertSame('New User', $user->name);
        $this->assertNull($user->password);
    }

    public function test_it_adopts_an_existing_user_with_the_same_email_but_a_different_clerk_id(): void
    {
        // حساب Clerk انحذف وانعمل من جديد => clerk_id جديد، نفس الإيميل.
        // قبل التصليح كان الـ INSERT يكسر users_email_unique ويرجع 500.
        $existing = User::factory()->create([
            'clerk_id' => 'user_old',
            'email' => 'fiez@example.com',
        ]);

        $resolved = $this->repository()->resolve('user_new', 'fiez@example.com', 'Fiez Alhag');

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame('user_new', $resolved->clerk_id);
        $this->assertSame('Fiez Alhag', $resolved->name);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_adopting_a_user_keeps_their_existing_role(): void
    {
        // أدمن أعاد إنشاء حسابه بـ Clerk - ما لازم يفقد صلاحيته.
        User::factory()->admin()->create([
            'clerk_id' => 'user_old',
            'email' => 'admin@example.com',
        ]);

        $resolved = $this->repository()->resolve('user_new', 'admin@example.com', 'Admin');

        $this->assertSame('admin', $resolved->role);
    }

    public function test_it_falls_back_to_a_placeholder_email_when_the_token_has_none(): void
    {
        // توكن Clerk الافتراضي ما بيحتوي claim للإيميل.
        $user = $this->repository()->resolve('user_no_email', null, '');

        $this->assertSame('user_no_email@placeholder.clerk', $user->email);
        $this->assertSame('Clerk User', $user->name);
    }

    public function test_placeholder_emails_do_not_collide_between_users(): void
    {
        $first = $this->repository()->resolve('user_one', null, null);
        $second = $this->repository()->resolve('user_two', null, null);

        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseCount('users', 2);
    }
}
