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
        Schema::create('next_leaves_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->enum('leave_type', ['hourly', 'daily', 'medical', 'without_pay'])->default('daily');
            $table->string('start_date_shamsi'); // تاریخ شروع (شمسی)
            $table->string('end_date_shamsi');   // تاریخ پایان (شمسی)
            $table->string('duration_text');     // مثلاً "۲ روز" یا "۴ ساعت"
            $table->text('reason')->nullable();    // علت مرخصی
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('supervisor_note')->nullable(); // توضیحات ناظر موقع رد یا تایید
            $table->timestamps();

            // کلیدهای خارجی برای حفظ یکپارچگی ارجاع داده‌ها
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('next_leaves_requests');
    }
};