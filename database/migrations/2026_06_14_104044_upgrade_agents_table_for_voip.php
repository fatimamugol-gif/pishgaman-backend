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
        Schema::table('agents', function (Blueprint $table) {
            // ۱. داخلی کارشناس در سیستم ایزابل (مثلاً 101, 102) برای مچ کردن تماس‌ها
            $table->string('voip_extension')->nullable()->unique()->after('email');
            
            // ۲. دپارتمان بر اساس فلوچارت جدید (call_center | contract_team | executive_team)
            $table->string('role')->default('call_center')->index()->after('voip_extension');
            
            // ۳. ذخیره مجموع ثانیه‌های مکالمه روزانه برای پرفورمنس‌سنجی و توزیع عادلانه لیدها
            $table->unsignedInteger('daily_talk_time_seconds')->default(0)->after('current_active_leads');
            
            // ۴. تعداد تماس‌های موفق و ناموفق امروز
            $table->unsignedInteger('daily_successful_calls')->default(0)->after('daily_talk_time_seconds');
            $table->unsignedInteger('daily_unanswered_calls')->default(0)->after('daily_successful_calls');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            //
        });
    }
};
