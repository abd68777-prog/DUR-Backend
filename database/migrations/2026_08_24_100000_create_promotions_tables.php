<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en');

            // null = عرض تلقائي بينطبق لحاله. غير null = الزبون لازم يدخّل الكود.
            $table->string('code')->nullable()->unique();

            $table->string('type');            // percentage | fixed
            $table->decimal('value', 10, 2);
            $table->string('scope');           // all | products | categories

            // null = مفتوح من الطرفين (بلا بداية أو بلا نهاية).
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // كل استعلام بيجيب العروض الشغّالة بيمرق من هدول التلاتة.
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        // الأسماء مرتّبة أبجدياً عن قصد - هيك Laravel بيشتق اسم جدول الربط
        // من اسمي الموديلين، فما بنحتاج نمرّره يدوياً بالعلاقة.
        Schema::create('product_promotion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unique(['promotion_id', 'product_id']);
        });

        Schema::create('category_promotion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unique(['promotion_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_promotion');
        Schema::dropIfExists('product_promotion');
        Schema::dropIfExists('promotions');
    }
};
