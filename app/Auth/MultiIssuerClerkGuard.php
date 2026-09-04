<?php

namespace App\Auth;

use Illuminate\Support\Carbon;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use RonasIT\Clerk\Auth\ClerkGuard;

/**
 * بيقبل توكنات من أكتر من Clerk instance بنفس الوقت.
 *
 * ليش: الفرونت لسه بمرحلة اختبار - الجهاز المحلي شغّال على instance التطوير
 * بينما الموقع المنشور صار يبعت توكنات الإنتاج، ولازم الاتنين يشتغلوا سوا لحد
 * ما يخلص الانتقال.
 *
 * الأصل بالباكج بيقارن مع issuer واحد وبيستخدم مفتاح واحد. هون منقرأ الـ iss
 * من التوكن ومنختار المفتاح المطابق إله، وأي iss مش بالقائمة بينرفض فوراً.
 *
 * مؤقت: فضّي CLERK_DEV_ISSUER بالـ .env وبيرجع لمصدر واحد لحاله.
 */
class MultiIssuerClerkGuard extends ClerkGuard
{
    protected function isValidToken(Token $decoded): bool
    {
        $now = Carbon::now();

        $origin = $decoded->claims()->get('azp');

        // نفس فحوصات الأب حرفياً، بس hasBeenIssuedBy بتاخد القائمة كاملة
        // (variadic أصلاً بالمكتبة). فحص الـ azp مقابل allowed_origins بيضل
        // متل ما هو - هو يلي بيمنع دومين غريب يستخدم توكن صالح.
        return ! $decoded->isExpired($now)
            && $decoded->hasBeenIssuedBefore($now)
            && $decoded->hasBeenIssuedBy(...$this->allowedIssuers())
            && (empty($origin) || in_array($origin, config('clerk.allowed_origins')))
            && $this->hasValidSignature($decoded);
    }

    protected function hasValidSignature(Token $decoded): bool
    {
        $signerKey = $this->signerKeyFor($decoded->claims()->get('iss'));

        // مصدر مش معروف = ما في مفتاح نتحقق فيه = رفض.
        if ($signerKey === null) {
            return false;
        }

        return (new Validator)->validate(
            $decoded,
            new SignedWith(new Sha256, $signerKey),
        );
    }

    /**
     * @return list<string>
     */
    protected function allowedIssuers(): array
    {
        return array_values(array_filter([
            config('clerk.allowed_issuer'),
            config('clerk.dev_issuer'),
        ]));
    }

    /**
     * المفتاح العام الخاص بهالمصدر بالذات.
     *
     * مهم إنه كل مصدر يتحقق بمفتاحه هو: لو استخدمنا مفتاح واحد للاتنين، توكن
     * موقّع من instance بيمرق على instance تاني.
     */
    protected function signerKeyFor(?string $issuer): ?Key
    {
        if (blank($issuer)) {
            return null;
        }

        if ($issuer === config('clerk.dev_issuer') && filled(config('clerk.dev_signer_key'))) {
            return InMemory::plainText(config('clerk.dev_signer_key'));
        }

        if ($issuer === config('clerk.allowed_issuer')) {
            // نفس منطق الأب بالضبط للمصدر الأساسي.
            return config('clerk.signer_key')
                ? InMemory::plainText(config('clerk.signer_key'), config('clerk.secret_key'))
                : InMemory::file(base_path(config('clerk.signer_key_path')), config('clerk.secret_key'));
        }

        return null;
    }
}
