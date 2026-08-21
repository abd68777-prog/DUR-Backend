<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetUserRoleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_promotes_a_user_to_admin_by_default(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'role' => 'customer']);

        $this->artisan('user:set-role', ['email' => 'owner@example.com'])
            ->assertExitCode(0);

        $this->assertSame('admin', $user->fresh()->role);
    }

    public function test_it_accepts_an_explicit_role(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com', 'role' => 'customer']);

        $this->artisan('user:set-role', ['email' => 'staff@example.com', 'role' => 'manager'])
            ->assertExitCode(0);

        $this->assertSame('manager', $user->fresh()->role);
    }

    public function test_it_rejects_an_invalid_role(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'role' => 'customer']);

        $this->artisan('user:set-role', ['email' => 'owner@example.com', 'role' => 'superadmin'])
            ->assertExitCode(1);

        $this->assertSame('customer', $user->fresh()->role);
    }

    public function test_it_fails_when_no_user_matches_the_email(): void
    {
        $this->artisan('user:set-role', ['email' => 'missing@example.com'])
            ->assertExitCode(1);
    }
}
