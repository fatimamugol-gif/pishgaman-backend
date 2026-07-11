<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('users', function (Blueprint $table) {
        // ۱. افزودن ستون نقش کاربر برای تفکیک سطوح دسترسی پیشگامان
        if (!Schema::hasColumn('users', 'role')) {
            $table->enum('role', ['supervisor', 'agent', 'client'])->default('client')->after('email');
        }
        
        // ۲. افزودن ستون رمز عبور هش شده (اگر از قبل وجود ندارد)
        if (!Schema::hasColumn('users', 'password')) {
            $table->string('password')->nullable()->after('role');
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
