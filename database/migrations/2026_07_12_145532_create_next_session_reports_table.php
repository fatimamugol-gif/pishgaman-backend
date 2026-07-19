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
        Schema::create('next_session_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id'); // کلاینت/لید متقاضی
            $table->unsignedBigInteger('agent_id'); // کارشناس ارشد مسئول تکمیل فرم
            
            // 👤 اطلاعات هویتی جلسه
            $table->string('client_name');
            $table->string('initial_agent_name'); // نام مشاور اولیه
            $table->string('senior_consultant_name'); // نام مشاور عالی (تخصصی)
            
            // ⏱️ پچ استراتژیک: زمان‌بندی صعودی بر پایه Timestamp جهت همگام‌سازی با زون جهانی Next.js و FCM
            $table->timestamp('session_start_at')->nullable();
            $table->timestamp('session_end_at')->nullable();
            $table->timestamp('deadline_at')->nullable(); // زمان پایان جلسه + ۲ ساعت
            
            // 📝 فیلدهای داینامیک و غنی فرم ارزیابی مشاور عالی
            $table->string('target_plan'); // پلن مد نظر متقاضی
            $table->text('special_conditions')->nullable(); // شرایط خاص متقاضی (بیماری، گپ تحصیلی و...)
            $table->text('strengths')->nullable(); // نقاط قوت رزومه
            $table->text('weaknesses')->nullable(); // نقاط ضعف و ریسک‌های پرونده
            $table->text('client_questions')->nullable(); // سوالات متقاضی
            $table->text('previous_actions')->nullable(); // اقدامات قبلی متقاضی
            $table->text('session_outcome'); // نتیجه جلسه حضوری
            $table->text('next_session_documents')->nullable(); // مدارک مورد نیاز برای جلسه بعد
            $table->text('senior_consultant_opinion'); // نظر کارشناسی مشاور ارشد/عالی
            $table->text('recommended_plans'); // پلن‌های توصیه شده سیستم
            
            // 🚦 وضعیت پلمب و کنترل ددلاین فرم توسط ناظر هوشمند
            $table->enum('status', ['pending', 'completed', 'expired'])->default('pending');
            $table->timestamp('submitted_at')->nullable(); // زمان دقیق تکمیل فرم توسط کارشناس
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 🛡️ اصلاح قطعی متد معکوس: حذف امن همین جدول بدون آسیب زدن به لیدها یا کاربران سیستم
        Schema::dropIfExists('next_session_reports');
    }
};