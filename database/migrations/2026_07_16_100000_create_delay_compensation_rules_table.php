<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // جدول قوانین جبران تاخیر
        Schema::create('next_delay_compensation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name'); // نام قانون
            $table->integer('delay_start_minutes')->default(0); // شروع بازه تاخیر (دقیقه)
            $table->integer('delay_end_minutes')->default(30); // پایان بازه تاخیر (دقیقه)
            $table->integer('compensation_minutes')->default(0); // دقیقه جبران خدمت مورد نیاز
            $table->boolean('auto_leave_hours')->default(false); // آیا مرخصی خودکار ثبت شود؟
            $table->integer('auto_leave_duration_hours')->default(0); // مدت مرخصی خودکار (ساعت)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // جدول ثبت جبران تاخیر کارکنان
        Schema::create('next_delay_compensations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('attendance_clock_id');
            $table->date('date');
            $table->integer('delay_minutes')->default(0); // مقدار تاخیر واقعی
            $table->integer('compensation_minutes_required')->default(0); // دقیقه جبران خدمت مورد نیاز
            $table->integer('compensation_minutes_completed')->default(0); // دقیقه جبران خدمت انجام شده
            $table->boolean('auto_leave_recorded')->default(false); // آیا مرخصی خودکار ثبت شد؟
            $table->unsignedBigInteger('auto_leave_request_id')->nullable(); // آی‌دی مرخصی ثبت شده
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('attendance_clock_id')->references('id')->on('next_attendance_clocks')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_delay_compensations');
        Schema::dropIfExists('next_delay_compensation_rules');
    }
};
