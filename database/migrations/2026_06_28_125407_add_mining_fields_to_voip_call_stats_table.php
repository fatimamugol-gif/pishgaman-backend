<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voip_call_stats', function (Blueprint $table) {
            // زمان مکالمه مفید و خالص (بدون احتساب بوق خوردن)
            $table->integer('billable_seconds')->default(0)->after('duration_seconds');
            
            // آخرین وضعیت عملیاتی یا اپلیکیشنی آستریسک (مثل Dial, Hangup, Busy)
            $table->string('last_application')->nullable()->after('disposition');
            
            // زمان دقیق و تفکیک‌شده مخابرات برای ماینینگ زمانی
            $table->timestamp('start_time')->nullable()->after('last_application');
            $table->timestamp('end_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('voip_call_stats', function (Blueprint $table) {
            $table->dropColumn(['billable_seconds', 'last_application', 'start_time', 'end_time']);
        });
    }
};