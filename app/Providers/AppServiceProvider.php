<?php

namespace App\Providers;

use App\Repositories\ClerkUserRepository;
use App\Services\PricingService;
use App\Services\PromotionService;
use Illuminate\Support\ServiceProvider;
use RonasIT\Clerk\Contracts\UserRepositoryContract;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // نسخة وحدة للطلب الواحد: PromotionService بيخزّن العروض الشغّالة بعد
        // أول استعلام، وProductResource بيسأله لكل منتج بالّستة. بدون هالربط
        // كل منتج بياخد نسخة جديدة وبيعيد الاستعلام - N+1 على طول.
        // scoped بدل singleton حتى الكاش ينمسح بين الطلبات لو انتقلنا لـ Octane.
        $this->app->scoped(PromotionService::class);
        $this->app->scoped(PricingService::class);
    }

    public function boot(): void
    {
        $this->app->bind(UserRepositoryContract::class, ClerkUserRepository::class);
    }
}
