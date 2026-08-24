<?php

/*
|--------------------------------------------------------------------------
| القيم الافتراضية لإعدادات المتجر
|--------------------------------------------------------------------------
|
| هي القيم يلي بتنزرع بجدول settings أول ما يشتغل الـ migration، وبتُستخدم
| كمان كقيم احتياطية لو انحذف الصف. مصدرها constants/index.ts تبع الفرونت.
|
| تعديلها هون ما بيغيّر شي على قاعدة بيانات شغّالة - الإدارة بتعدّل عبر
| PUT /api/settings. هي بس نقطة البداية.
|
*/

return [

    'defaults' => [

        'brand' => [
            'site_url' => 'https://durjewels.com',
            // أرقام فقط بدون + وبدون مسافات - الفرونت بيبنيها wa.me/{الرقم}
            'whatsapp_number' => '963999000000',
        ],

        'contact' => [
            'phone' => '+963 999 000 000',
            // منفصل عن phone عن قصد: شكل العرض غير شكل الـ tel: scheme
            'phone_href' => 'tel:+963999000000',
            'email' => 'info@dur-jewelry.com',
            'address_ar' => 'دمشق، سوريا',
            'address_en' => 'Damascus, Syria',
        ],

        // ما في حقل واتساب هون عن قصد - رابطه بينبنى من brand.whatsapp_number
        'social' => [
            'instagram_url' => 'https://instagram.com/dur.jewelry',
            'facebook_url' => 'https://facebook.com/dur.jewelry',
        ],

        'home_hero' => [
            'video_url' => null,
            'poster_url' => null,
            'title_line1_ar' => 'أجواء فاخرة',
            'title_line1_en' => 'Elegant Atmosphere',
            'title_line2_ar' => 'وتجربة تسوق أنيقة',
            'title_line2_en' => 'For Premium Shopping',
            'description_ar' => 'تشكيلة مختارة بعناية لتليق بذوقك وتجربة شراء راقية ومريحة',
            'description_en' => 'A curated collection for a refined shopping experience.',
            'primary_button_label_ar' => 'تسوق الآن',
            'primary_button_label_en' => 'Shop',
            'primary_button_url' => '/products',
            'secondary_button_label_ar' => 'اكتشف المزيد',
            'secondary_button_label_en' => 'Explore',
            'secondary_button_url' => '/#about',
        ],

    ],

    'media' => [
        // Cloudinary بيقبل 100MB للفيديو بالخطة المجانية، بس PHP وnginx عالسيرفر
        // لازم يسمحوا بالحجم كمان (upload_max_filesize / client_max_body_size).
        'max_video_kb' => 51200,   // 50MB
        'max_poster_kb' => 4096,   // 4MB
    ],

];
