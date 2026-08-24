<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * خصم يدوي لكل منتج على حدة - الآلية المعتمدة بالنسخة الحالية، لأن الطلبات
 * بتتم عبر واتساب وما في سلة مشتريات.
 *
 * جدول promotions بيضل موجود للتوسّع المستقبلي؛ الاتنين بينغذّوا final_price
 * وبيربح الأكبر خصماً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // عمود منفصل عن القيمة حتى الإدارة تقدر تطفي الخصم مؤقتاً
            // وترجّعه بدون ما تفقد النسبة يلي كانت محطوطة.
            $table->boolean('has_discount')->default(false)->after('price');

            // نسبة مئوية (0.01 - 100). decimal(5,2) بتسع 100.00 مع كسور.
            $table->decimal('discount_value', 5, 2)->nullable()->after('has_discount');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['has_discount', 'discount_value']);
        });
    }
};
