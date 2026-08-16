<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // تفاصيل المجوهرات 
            $table->decimal('gold_weight', 8, 3)->nullable();
            $table->enum('karat', ['18', '21', '24'])->nullable();
            $table->string('gemstone_type')->nullable();
            $table->decimal('gemstone_carat', 6, 2)->nullable();

            // السعر يدوي بالكامل
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock')->default(0);

            // الإخفاء بدون حذف
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
