<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // 👤 اطلاعات هویتی و عمومی متقاضی (تصویر اول)
            if (!Schema::hasColumn('leads', 'first_name')) $table->string('first_name')->nullable();
            if (!Schema::hasColumn('leads', 'last_name')) $table->string('last_name')->nullable();
            if (!Schema::hasColumn('leads', 'age')) $table->integer('age')->nullable();
            if (!Schema::hasColumn('leads', 'marital_status')) $table->string('marital_status')->nullable(); // مجرد / متاهل
            if (!Schema::hasColumn('leads', 'military_status')) $table->string('military_status')->nullable(); // وضعیت نظام وظیفه
            
            // 🎓 سوابق تحصیلی متقاضی (تصویر دوم)
            if (!Schema::hasColumn('leads', 'last_degree')) $table->string('last_degree')->nullable(); // آخرین مدرک تحصیلی
            if (!Schema::hasColumn('leads', 'gpa')) $table->decimal('gpa', 4, 2)->nullable(); // معدل متقاضی
            if (!Schema::hasColumn('leads', 'graduation_year')) $table->integer('graduation_year')->nullable(); // سال فارغ‌التحصیلی
            if (!Schema::hasColumn('leads', 'field_of_study')) $table->string('field_of_study')->nullable(); // رشته تحصیلی
            
            // 🌐 مدرک زبان و وضعیت تمکن مالی (تصویر سوم)
            if (!Schema::hasColumn('leads', 'language_degree')) $table->string('language_degree')->nullable(); // نوع مدرک (IELTS, Duolingo, ...)
            if (!Schema::hasColumn('leads', 'language_score')) $table->string('language_score')->nullable(); // نمره مدرک زبان
            if (!Schema::hasColumn('leads', 'financial_capability')) $table->bigInteger('financial_capability')->default(0); // میزان تمکن مالی (به ریال/دلار)
            if (!Schema::hasColumn('leads', 'has_job_offer')) $table->boolean('has_job_offer')->default(false); // آیا جاب آفر دارد؟
            
            // 📊 اطلاعات استراتژیک بیزینسی
            if (!Schema::hasColumn('leads', 'target_country')) $table->string('target_country')->nullable(); // کشور مقصد نهایی
            if (!Schema::hasColumn('leads', 'visa_type')) $table->string('visa_type')->nullable(); // نوع ویزای درخواستی
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // در صورت رول‌بک فیلدها حذف می‌شوند
        });
    }
};