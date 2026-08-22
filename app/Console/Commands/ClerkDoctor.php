<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lcobucci\JWT\Signer\Key\InMemory;
use RonasIT\Clerk\Auth\ClerkGuard;
use Throwable;

/**
 * بيفحص كل شي بيخلي `GET /api/user` يرجع 500 بدل ما يشتغل.
 * ما بيطبع ولا قيمة سرية - بس "مضبوط / ناقص".
 */
class ClerkDoctor extends Command
{
    protected $signature = 'clerk:doctor {--log : اطبع كمان آخر الأخطاء من laravel.log}';

    protected $description = 'Diagnose Clerk authentication configuration on this server';

    private bool $failed = false;

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>Clerk / environment diagnostics</>');
        $this->newLine();

        $this->checkRequiredConfig();
        $this->checkSignerKey();
        $this->checkGuard();
        $this->checkOrigins();
        $this->checkLocale();
        $this->checkDatabase();

        if ($this->option('log')) {
            $this->showRecentErrors();
        }

        $this->newLine();

        if ($this->failed) {
            $this->error('Some checks failed. Fix the items marked FAIL, then run: php artisan config:cache');

            return self::FAILURE;
        }

        $this->info('All checks passed.');

        return self::SUCCESS;
    }

    private function checkRequiredConfig(): void
    {
        // فاضيين = ClerkGuard بيرمي EmptyConfigException بالـ constructor => 500
        $this->assert('CLERK_ALLOWED_ISSUER is set', filled(config('clerk.allowed_issuer')));
        $this->assert('CLERK_SECRET_KEY is set', filled(config('clerk.secret_key')));
        $this->assert('CLERK_WEBHOOK_SECRET is set', filled(config('services.clerk.webhook_secret')), warnOnly: true);
    }

    private function checkSignerKey(): void
    {
        $inlineKey = config('clerk.signer_key');

        if (filled($inlineKey)) {
            $this->assert('CLERK_SIGNER_KEY is set (inline key)', true);
        } else {
            // clerk.pem متجاهل بالـ git عن قصد، فلازم ينرفع يدوياً عالسيرفر.
            // إذا ناقص، InMemory::file() بترمي استثناء => 500 على كل طلب مصادَق.
            $path = base_path(config('clerk.signer_key_path'));

            $this->assert(
                "signer key file exists: {$path}",
                is_file($path) && is_readable($path)
            );
        }

        try {
            filled($inlineKey)
                ? InMemory::plainText($inlineKey, config('clerk.secret_key'))
                : InMemory::file(base_path(config('clerk.signer_key_path')), config('clerk.secret_key'));

            $this->assert('signer key loads without error', true);
        } catch (Throwable $e) {
            $this->assert('signer key loads without error', false, class_basename($e).': '.$e->getMessage());
        }
    }

    private function checkGuard(): void
    {
        try {
            new ClerkGuard;
            $this->assert('ClerkGuard can be constructed', true);
        } catch (Throwable $e) {
            $this->assert('ClerkGuard can be constructed', false, class_basename($e).': '.$e->getMessage());
        }
    }

    private function checkOrigins(): void
    {
        // الدومينات مش أسرار، فمنيح نطبعها - غالباً هون بتكون المشكلة.
        $clerkOrigins = array_filter((array) config('clerk.allowed_origins'));
        $corsOrigins = array_filter((array) config('cors.allowed_origins'));

        $this->assert('CLERK_ALLOWED_ORIGINS is set', filled($clerkOrigins));
        $this->line('       clerk: '.($clerkOrigins ? implode(', ', $clerkOrigins) : '(empty)'));

        $this->assert('CORS_ALLOWED_ORIGINS is set', filled($corsOrigins));
        $this->line('       cors:  '.($corsOrigins ? implode(', ', $corsOrigins) : '(empty)'));

        if (app()->environment('production')) {
            $localhost = array_filter(
                array_merge($clerkOrigins, $corsOrigins),
                fn ($origin) => str_contains($origin, 'localhost') || str_contains($origin, '127.0.0.1')
            );

            $this->assert(
                'no localhost origins in production',
                empty($localhost),
                'found: '.implode(', ', $localhost),
                warnOnly: true
            );
        }
    }

    private function checkLocale(): void
    {
        // لو الـ locale مش en وما في مجلد lang/{locale}، رسائل الأخطاء بترجع
        // كمفاتيح خام متل "validation.boolean" بدل نص مفهوم.
        $locale = config('app.locale');
        $hasTranslations = $locale === 'en' || is_dir(base_path("lang/{$locale}"));

        $this->assert(
            "APP_LOCALE ({$locale}) has translations",
            $hasTranslations,
            "no lang/{$locale} directory - validation messages will show raw keys",
            warnOnly: true
        );
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->assert('database connection', true);
        } catch (Throwable $e) {
            $this->assert('database connection', false, class_basename($e).': '.$e->getMessage());

            return;
        }

        $this->assert("'role' column exists on users", Schema::hasColumn('users', 'role'));
        $this->assert("'clerk_id' column exists on users", Schema::hasColumn('users', 'clerk_id'));

        $password = collect(Schema::getColumns('users'))->firstWhere('name', 'password');

        $this->assert(
            "'password' column is nullable",
            (bool) ($password['nullable'] ?? false),
            'run: php artisan migrate --force'
        );
    }

    private function showRecentErrors(): void
    {
        $this->newLine();
        $this->line('<options=bold>Recent errors (laravel.log)</>');

        $path = storage_path('logs/laravel.log');

        if (! is_file($path)) {
            $this->line('  no log file at '.$path);

            return;
        }

        // بس أسطر العنوان - أسطر الـ stack trace ممكن تحوي أجزاء من مفاتيح.
        $headers = collect(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            ->filter(fn ($line) => str_contains($line, '.ERROR:'))
            ->take(-10);

        if ($headers->isEmpty()) {
            $this->line('  no errors logged.');

            return;
        }

        foreach ($headers as $line) {
            $this->line('  '.mb_substr($line, 0, 400));
        }
    }

    private function assert(string $label, bool $passed, ?string $hint = null, bool $warnOnly = false): void
    {
        if ($passed) {
            $this->line("  <fg=green>[ OK ]</> {$label}");

            return;
        }

        if ($warnOnly) {
            $this->line("  <fg=yellow>[WARN]</> {$label}".($hint ? " - {$hint}" : ''));

            return;
        }

        $this->failed = true;
        $this->line("  <fg=red>[FAIL]</> {$label}".($hint ? " - {$hint}" : ''));
    }
}
