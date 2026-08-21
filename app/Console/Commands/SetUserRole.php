<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserRole extends Command
{
    protected $signature = 'user:set-role {email} {role=admin}';

    protected $description = 'Set a user\'s role (admin, manager, or customer) by email';

    public function handle(): int
    {
        $email = $this->argument('email');
        $role = $this->argument('role');

        if (! in_array($role, ['admin', 'manager', 'customer'], true)) {
            $this->error("Invalid role \"{$role}\". Allowed: admin, manager, customer.");

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email \"{$email}\". They must sign in at least once before their account exists.");

            return self::FAILURE;
        }

        $user->update(['role' => $role]);

        $this->info("{$user->name} ({$email}) is now \"{$role}\".");

        return self::SUCCESS;
    }
}
