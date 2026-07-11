<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // ۱. لایه اطلاعات اصلی و تماسی (اگر از قبل نباشند اضافه می‌شوند)
            if (!Schema::hasColumn('leads', 'secondary_phone')) $table->string('secondary_phone')->nullable()->comment('16. شماره دوم');
            if (!Schema::hasColumn('leads', 'current_city')) $table->string('current_city')->nullable()->comment('33. شهر محل سکونت فعلی');
            
            // ۲. لایه لید و مدیریت کارشناسان (فیلدهای ۱، ۲، ۳، ۴، ۶، ۸، ۹، ۱۳، ۳۷، ۴۰)
            if (!Schema::hasColumn('leads', 'initial_consultation_status')) $table->string('initial_consultation_status')->default('pending')->comment('1. وضعیت مشاوره اولیه');
            if (!Schema::hasColumn('leads', 'lead_source')) $table->string('lead_source')->nullable()->comment('2. منبع و سورس لید');
            if (!Schema::hasColumn('leads', 'assigned_agent_id')) $table->unsignedBigInteger('assigned_agent_id')->nullable()->comment('3. منتصب به کدام کارشناس');
            if (!Schema::hasColumn('leads', 'tags')) $table->json('tags')->nullable()->comment('4. برچسب‌ها');
            if (!Schema::hasColumn('leads', 'call_today_flag')) $table->boolean('call_today_flag')->default(false)->comment('6. تیک تماس امروز');
            if (!Schema::hasColumn('leads', 'initial_consulted_by_id')) $table->unsignedBigInteger('initial_consulted_by_id')->nullable()->comment('8. مشاوره اولیه توسط (کال سنتر)');
            if (!Schema::hasColumn('leads', 'senior_consultant_id')) $table->unsignedBigInteger('senior_consultant_id')->nullable()->comment('9. مشاور اولیه (مشاوران عالی)');
            if (!Schema::hasColumn('leads', 'contract_agent_id')) $table->unsignedBigInteger('contract_agent_id')->nullable()->comment('13. کارشناس قرارداد');
            if (!Schema::hasColumn('leads', 'discovery_channel')) $table->string('discovery_channel')->nullable()->comment('37. از چه راهی با ما آشنا شدید');
            if (!Schema::hasColumn('leads', 'supervisor_status')) $table->string('supervisor_status')->nullable()->comment('40. ناظر');
            if (!Schema::hasColumn('leads', 'import_source')) $table->string('import_source')->default('next_front')->index()->comment('تفکیک فرانت از پرفکس');

            // ۳. لایه زمان‌بندی و دیت‌پیکرها (فیلدهای ۷، ۱۰، ۱۱، ۱۲، ۳۹)
            if (!Schema::hasColumn('leads', 'preferred_call_time')) $table->string('preferred_call_time')->nullable()->comment('7. بازه تماس با متقاضی');
            if (!Schema::hasColumn('leads', 'specialized_consultation_status')) $table->string('specialized_consultation_status')->nullable()->comment('10. وضعیت مشاوره تخصصی');
            if (!Schema::hasColumn('leads', 'session_date_shamsi')) $table->string('session_date_shamsi')->nullable()->comment('11. تاریخ جلسه (شمسی)');
            if (!Schema::hasColumn('leads', 'client_conversion_date_shamsi')) $table->string('client_conversion_date_shamsi')->nullable()->comment('12. تاریخ تبدیل به کلاینت (شمسی)');
            if (!Schema::hasColumn('leads', 'next_call_date_shamsi')) $table->string('next_call_date_shamsi')->nullable()->comment('39. تماس بعدی ☎️');

            // ۴. لایه سوابق متقاضی (فیلدهای ۱۷، ۱۹، ۲۰، ۲۱، ۲۲، ۲۳، ۲۴، ۳۱، ۳۲، ۳۴، ۳۵، ۳۶، ۳۸)
            if (!Schema::hasColumn('leads', 'age')) $table->integer('age')->nullable()->comment('17. سن');
            if (!Schema::hasColumn('leads', 'education_level')) $table->string('education_level')->nullable()->comment('19. تحصیلات');
            if (!Schema::hasColumn('leads', 'requested_plan')) $table->string('requested_plan')->nullable()->comment('20. پلن مورد درخواست');
            if (!Schema::hasColumn('leads', 'english_level')) $table->string('english_level')->nullable()->comment('21. سطح انگلیسی');
            if (!Schema::hasColumn('leads', 'german_level')) $table->string('german_level')->nullable()->comment('22. سطح آلمانی');
            if (!Schema::hasColumn('leads', 'english_certified_level')) $table->string('english_certified_level')->nullable()->comment('36. انگلیسی با مدرک');
            if (!Schema::hasColumn('leads', 'german_certified_level')) $table->string('german_certified_level')->nullable()->comment('35. آلمانی با مدرک');
            if (!Schema::hasColumn('leads', 'language_test_history')) $table->json('language_test_history')->nullable()->comment('23. سوابق آزمون');
            if (!Schema::hasColumn('leads', 'work_and_insurance_history')) $table->text('work_and_insurance_history')->nullable()->comment('24. سوابق بیمه');
            if (!Schema::hasColumn('leads', 'target_country')) $table->string('target_country')->nullable()->comment('31. کشور مورد درخواست');
            if (!Schema::hasColumn('leads', 'financial_capability_toman')) $table->bigInteger('financial_capability_toman')->default(0)->comment('32. تمکن مالی (تومان)');
            if (!Schema::hasColumn('leads', 'military_status')) $table->string('military_status')->nullable()->comment('34. وضعیت پایان خدمت');
            if (!Schema::hasColumn('leads', 'description')) $table->text('description')->nullable()->comment('38. توضیحات فرعی');

            // ۵. لایه اطلاعات همسر و خانواده (فیلدهای ۲۵، ۲۶، ۲۷، ۲۸، ۲۹، ۳۰)
            if (!Schema::hasColumn('leads', 'spouse_name')) $table->string('spouse_name')->nullable()->comment('26. نام همسر');
            if (!Schema::hasColumn('leads', 'spouse_birth_date_shamsi')) $table->string('spouse_birth_date_shamsi')->nullable()->comment('27. تولد همسر');
            if (!Schema::hasColumn('leads', 'spouse_education')) $table->string('spouse_education')->nullable()->comment('28. تحصیلات همسر');
            if (!Schema::hasColumn('leads', 'children_count')) $table->integer('children_count')->default(0)->comment('29. تعداد فرزند');
            if (!Schema::hasColumn('leads', 'spouse_work_history')) $table->text('spouse_work_history')->nullable()->comment('30. سابقه کار همسر');
        });
    }

    public function down(): void
    {
        // رول‌بک
    }
};