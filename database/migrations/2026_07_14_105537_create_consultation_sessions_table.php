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
        Schema::create('consultation_sessions', function (Blueprint $table) {
            $table->id();
            
            // ارتباط با لید
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            
            // ارتباط با کارشناس/مشاور
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            
            // اطلاعات جلسه
            $table->date('session_date')->nullable();
            $table->string('session_type')->default('initial'); // initial, followup, etc.
            $table->string('status')->default('scheduled'); // scheduled, completed, cancelled
            $table->text('notes')->nullable();
            
            // 👤 اطلاعات هویتی و عمومی متقاضی
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->integer('age')->nullable();
            $table->string('marital_status')->nullable(); // مجرد / متاهل
            $table->string('military_status')->nullable(); // وضعیت نظام وظیفه
            
            // 🎓 سوابق تحصیلی متقاضی
            $table->string('last_degree')->nullable(); // آخرین مدرک تحصیلی
            $table->decimal('gpa', 4, 2)->nullable(); // معدل متقاضی
            $table->integer('graduation_year')->nullable(); // سال فارغ‌التحصیلی
            $table->string('field_of_study')->nullable(); // رشته تحصیلی
            
            // 🌐 مدرک زبان و وضعیت تمکن مالی
            $table->string('language_degree')->nullable(); // نوع مدرک (IELTS, Duolingo, ...)
            $table->string('language_score')->nullable(); // نمره مدرک زبان
            $table->bigInteger('financial_capability')->default(0); // میزان تمکن مالی (به ریال/دلار)
            $table->boolean('has_job_offer')->default(false); // آیا جاب آفر دارد؟
            
            // 📊 اطلاعات استراتژیک بیزینسی
            $table->string('target_country')->nullable(); // کشور مقصد نهایی
            $table->string('visa_type')->nullable(); // نوع ویزای درخواستی
            
            // اطلاعات همسر
            $table->string('spouse_name')->nullable();
            $table->string('spouse_phone')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation_sessions');
    }
};
