<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('next_session_reports', function (Blueprint $table) {
            // ۱. حذف ستون‌های استرینگ قدیمی
            $table->dropColumn(['initial_agent_name', 'senior_consultant_name']);

            // ۲. اضافه کردن ستون‌های کلید خارجی برای مشاور اولیه و مشاور عالی
            $table->unsignedBigInteger('initial_agent_id')->nullable()->after('client_name');
            $table->unsignedBigInteger('senior_agent_id')->nullable()->after('initial_agent_id');

            // ۳. ایجاد ارتباطات رسمی دیتابیس با جدول agents
            $table->foreign('initial_agent_id')->references('id')->on('agents')->onDelete('set null');
            $table->foreign('senior_agent_id')->references('id')->on('agents')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('next_session_reports', function (Blueprint $table) {
            $table->dropForeign(['initial_agent_id']);
            $table->dropForeign(['senior_agent_id']);
            $table->dropColumn(['initial_agent_id', 'senior_agent_id']);
            
            $table->string('initial_agent_name')->after('client_name');
            $table->string('senior_consultant_name')->after('initial_agent_name');
        });
    }
};