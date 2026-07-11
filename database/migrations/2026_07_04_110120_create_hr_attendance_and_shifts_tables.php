<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ۱. جدول تعریف شیفت‌های کاری شرکت
        Schema::create('next_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // مثلاً "شیفت ثابت صبح"
            $table->string('shift_start')->default('08:00'); // ساعت شروع
            $table->string('shift_end')->default('16:00');   // ساعت پایان
            $table->integer('allowed_delay_minutes')->default(15); // تعجیل/تاخیر مجاز
            $table->timestamps();
        });

        // ۲. جدول تقویم تعطیلات رسمی (توسط ادمین پر می‌شود)
        Schema::create('next_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('holiday_date_shamsi')->unique(); // مثلاً "1405/01/01"
            $table->string('title'); // علت تعطیلی مثلاً "عید نوروز"
            $table->timestamps();
        });

        // ۳. جدول لایو ثبت ورود و خروج‌های مکرر کارشناسان (تایمر زنده)
        Schema::create('next_attendance_clocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('date_shamsi'); // تاریخ روز مثلاً "1405/04/14"
            $table->timestamp('clock_in')->nullable();  // زمان دقیق ورود
            $table->timestamp('clock_out')->nullable(); // زمان دقیق خروجی
            $table->integer('duration_seconds')->default(0); // محاسبه کارکرد این بازه به ثانیه
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_attendance_clocks');
        Schema::dropIfExists('next_holidays');
        Schema::dropIfExists('next_shifts');
    }
};