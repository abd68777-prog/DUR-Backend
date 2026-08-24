<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات المتجر - صف واحد ثابت (id = 1)، مش جدول سجلات.
 *
 * كل قسم عمود JSON بدل ما يكون كل حقل عمود لحاله: الشكل بيطابق الرد يلي
 * بياخده الفرونت حرف بحرف، وإضافة حقل جديد لقسم موجود ما بتحتاج migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->json('brand');
            $table->json('contact');
            $table->json('social');
            $table->json('home_hero');
            $table->timestamps();
        });

        // منزرع الصف الوحيد فوراً حتى GET /api/settings يشتغل من أول لحظة
        // بدون ما الإدارة تضطر تعبّي شي.
        $defaults = config('settings.defaults');
        $now = now();

        DB::table('settings')->insert([
            'id' => 1,
            'brand' => json_encode($defaults['brand'], JSON_UNESCAPED_UNICODE),
            'contact' => json_encode($defaults['contact'], JSON_UNESCAPED_UNICODE),
            'social' => json_encode($defaults['social'], JSON_UNESCAPED_UNICODE),
            'home_hero' => json_encode($defaults['home_hero'], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
