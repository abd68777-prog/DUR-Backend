<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('name', 'name_ar');
            $table->renameColumn('description', 'description_ar');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('id');
            $table->string('name_en')->nullable()->after('name_ar');
            $table->text('description_en')->nullable()->after('description_ar');
        });

        // enum columns are painful to evolve across engines; drop the DB-level
        // constraint and let StoreProductRequest/UpdateProductRequest own the
        // allowed karat values (18/21/22/24) instead.
        Schema::table('products', function (Blueprint $table) {
            $table->string('karat', 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'name_en', 'description_en']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('name_ar', 'name');
            $table->renameColumn('description_ar', 'description');
        });
    }
};
