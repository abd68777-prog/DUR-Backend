<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No-op: create_products_table already defines the final schema
     * (category_id, nullable gold_weight/karat, no making_charge/image).
     * This migration only remains so its "Ran" record in existing databases
     * stays valid; running it on a fresh database must not fail.
     */
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
