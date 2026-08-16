<?php

namespace App\Providers;

use App\Repositories\ClerkUserRepository;
use Illuminate\Support\ServiceProvider;
use RonasIT\Clerk\Contracts\UserRepositoryContract;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->bind(UserRepositoryContract::class, ClerkUserRepository::class);
    }
}
