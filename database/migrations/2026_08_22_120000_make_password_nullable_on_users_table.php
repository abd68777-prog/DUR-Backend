<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * المصادقة كلها عبر Clerk، فما في كلمة سر بتنخزن عنا.
     * كنا نعبّي العمود بكلمة سر عشوائية بس لأنه NOT NULL - هلق صار nullable.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        // ما فينا نرجّع العمود NOT NULL وفي صفوف قيمتها null.
        DB::table('users')->whereNull('password')->update([
            'password' => bcrypt(str()->random(32)),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
