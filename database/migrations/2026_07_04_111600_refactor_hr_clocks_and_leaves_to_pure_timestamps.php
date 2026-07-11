<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // لایروبی و پاکسازی ساختارهای قدیمی جهت پلمب بدون تعارض
        Schema::dropIfExists('next_attendance_clocks');
        Schema::dropIfExists('next_leaves_requests');

        // ۱. جدول فوق‌مدرن ثبت لایو کلک‌ها بر پایه تایم‌استمپ خام یونیکس
        Schema::create('next_attendance_clocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->bigInteger('clock_in_timestamp');  // تایم‌استمپ زمان ورود (عدد)
            $table->bigInteger('clock_out_timestamp')->nullable(); // تایم‌استمپ زمان خروج (عدد)
            $table->integer('duration_seconds')->default(0); // تفاضل ثانیه‌ای کارکرد مفید
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ۲. جدول مستقل مدیریت انواع مرخصی و ماموریت‌ها
        Schema::create('next_leaves_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->enum('leave_type', ['daily_vacation', 'hourly_pass', 'medical', 'mission', 'without_pay'])->default('daily_vacation');
            $table->bigInteger('start_timestamp'); // شروع مرخصی به تایم‌استمپ
            $table->bigInteger('end_timestamp');   // پایان مرخصی به تایم‌استمپ
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('supervisor_note')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_leaves_requests');
        Schema::dropIfExists('next_attendance_clocks');
    }
};