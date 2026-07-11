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
       

        // ۲. 🎯 اضافه کردن گارد کنترلی سیستم به جدول ترددهای پرسنل
        Schema::table('next_attendance_clocks', function (Blueprint $table) {
            $table->boolean('is_auto_closed')
                  ->default(false)
                  ->after('duration_seconds')
                  ->comment('پرچم تشخیص بسته‌شدن خودکار تایمر توسط گارد سیستم در صورت فراموشی کارمند');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        

        // ۲. 🛑 حذف فیلد کنترلی از جدول ترددها در صورت رول‌بک
        Schema::table('next_attendance_clocks', function (Blueprint $table) {
            $table->dropColumn('is_auto_closed');
        });
    }
};