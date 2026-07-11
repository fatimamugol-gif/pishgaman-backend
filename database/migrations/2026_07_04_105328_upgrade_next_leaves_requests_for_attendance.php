<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('next_leaves_requests', function (Blueprint $table) {
            // اضافه کردن پشتیبانی از ساعت برای پاس ساعتی و تردد روزانه
            $table->string('start_time')->nullable()->after('start_date_shamsi'); // مثلاً "08:00"
            $table->string('end_time')->nullable()->after('end_date_shamsi');   // مثلاً "17:00"
            $table->boolean('is_holiday_override')->default(false); // تیک روز تعطیل رسمی
        });
    }

    public function down(): void
    {
        Schema::table('next_leaves_requests', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'is_holiday_override']);
        });
    }
};