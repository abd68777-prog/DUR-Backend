<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ClerkDoctorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_origins_allowed_by_cors_but_not_by_clerk(): void
    {
        // الحالة يلي وقعت فعلياً: الفرونت شغّال على localhost:3000، مسموح بالـ
        // CORS بس ناقص بـ Clerk => التوكن مرفوض وكل طلب مصادَق بيفشل.
        Config::set('clerk.allowed_origins', ['https://durjewels.com']);
        Config::set('cors.allowed_origins', ['https://durjewels.com', 'http://localhost:3000']);

        $this->artisan('clerk:doctor')
            ->expectsOutputToContain('missing from CLERK_ALLOWED_ORIGINS: http://localhost:3000');
    }

    public function test_it_reports_origins_allowed_by_clerk_but_not_by_cors(): void
    {
        Config::set('clerk.allowed_origins', ['https://durjewels.com', 'https://www.durjewels.com']);
        Config::set('cors.allowed_origins', ['https://durjewels.com']);

        $this->artisan('clerk:doctor')
            ->expectsOutputToContain('missing from CORS_ALLOWED_ORIGINS: https://www.durjewels.com');
    }

    public function test_it_stays_quiet_when_both_origin_lists_match(): void
    {
        $origins = ['https://durjewels.com', 'https://www.durjewels.com'];

        Config::set('clerk.allowed_origins', $origins);
        Config::set('cors.allowed_origins', $origins);

        $this->artisan('clerk:doctor')
            ->doesntExpectOutputToContain('missing from CLERK_ALLOWED_ORIGINS')
            ->doesntExpectOutputToContain('missing from CORS_ALLOWED_ORIGINS');
    }

    public function test_it_flags_a_non_nullable_password_column(): void
    {
        $this->artisan('clerk:doctor')
            ->expectsOutputToContain("'password' column is nullable");
    }
}
