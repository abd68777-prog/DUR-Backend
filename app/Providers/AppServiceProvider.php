<?php

namespace App\Providers;

use App\Auth\MultiIssuerClerkGuard;
use App\Repositories\ClerkUserRepository;
use App\Services\PricingService;
use App\Services\PromotionService;
use Illuminate\Support\ServiceProvider;
use RonasIT\Clerk\Auth\ClerkGuard;
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

        // الباكج بيحلّ الـ guard من الـ container (Auth::extend بـ
        // ClerkServiceProvider)، فهالربط لحاله بيكفي - ما بدنا نعيد تسجيل
        // الـ driver ولا نلمس config/auth.php.
        $this->app->bind(ClerkGuard::class, MultiIssuerClerkGuard::class);
    }

    public function boot(): void
    {
        $this->app->bind(UserRepositoryContract::class, ClerkUserRepository::class);
    }
}
