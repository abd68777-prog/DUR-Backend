<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Token issuer, url
    |--------------------------------------------------------------------------
    | You can find your Clerk Frontend API URL in your Clerk dashboard under:
    | "Configure" -> "API keys" -> "Frontend API URL"
    */
    'allowed_issuer' => env('CLERK_ALLOWED_ISSUER'),

    /*
    |--------------------------------------------------------------------------
    | Token origin, OPTIONAL list of URLs separated by ","
    |--------------------------------------------------------------------------
    | Your client app origins, are highly recommended to set when using Web client applications.
    */
    // trim ضروري: مسافة وحدة بعد الفاصلة بتخلي الـ origin ما يطابق claim الـ azp
    // بالتوكن، فكل طلب من هالدومين بيرجع 401 بدون سبب واضح.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CLERK_ALLOWED_ORIGINS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Secret key, string
    |--------------------------------------------------------------------------
    | Your API secret key, needed to verify the token signature, can be found
    | in your Clerk dashboard under:
    | "Configure" -> "API keys" -> "Secret keys"
    */
    'secret_key' => env('CLERK_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Public key, string
    |--------------------------------------------------------------------------
    | Your public JWT key PEM content. You can find it in your Clerk dashboard under:
    | "Configure" -> "API keys" -> "JWKS Public Key"
    | Takes priority over signer_key_path if set.
    */
    'signer_key' => env('CLERK_SIGNER_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Public key path, filepath starting from base_path
    |--------------------------------------------------------------------------
    | Path to your public JWT key file. Used as a fallback when signer_key is not set.
    */
    'signer_key_path' => env('CLERK_SIGNER_KEY_PATH', 'clerk.pem'),

    /*
    |--------------------------------------------------------------------------
    | مصدر توكن إضافي - مؤقت
    |--------------------------------------------------------------------------
    | القيم فوق هي بيئة الإنتاج. هدول بيسمحوا مؤقتاً بقبول توكنات جاية من
    | instance تاني بنفس الوقت، لأن الفرونت لسه بمرحلة اختبار: الجهاز المحلي
    | شغّال على مفاتيح التطوير بينما الموقع المنشور صار يبعت توكنات الإنتاج.
    |
    | signer_key هون هو محتوى الـ PEM نصّاً (Clerk dashboard -> API keys ->
    | JWKS Public Key)، مش مسار ملف - حتى ما يصير في ملف تاني لازم ينرفع
    | يدوياً عالسيرفر.
    |
    | لإلغاء دعم المصدر التاني: احذف CLERK_DEV_ISSUER من .env وشغّل
    | php artisan config:cache. ما بدو ولا تعديل كود.
    */
    'dev_issuer' => env('CLERK_DEV_ISSUER'),

    // str_replace احتياطي: بيشتغل سواء فسّر phpdotenv الـ \n أو مرّرها حرفياً.
    'dev_signer_key' => str_replace('\n', "\n", (string) env('CLERK_DEV_SIGNER_KEY')),
];
