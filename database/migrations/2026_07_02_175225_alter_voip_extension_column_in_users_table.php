<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 🎯 ارتقای قطعی و داینامیک طول ستون به ۲۵۵ کاراکتر بدون ریزش داده‌های قبلی
            $table->string('voip_extension', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // بازگرداندن به حالت پیش‌فرض در صورت رول‌بک
            $table->string('voip_extension', 10)->nullable()->change();
        });
    }
};