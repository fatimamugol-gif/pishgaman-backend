<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ۱. ارتقای جدول اصلی تسک‌ها برای پشتیبانی از ویژگی‌های ترلو
        if (Schema::hasTable('next_tasks')) {
            Schema::table('next_tasks', function (Blueprint $table) {
                if (!Schema::hasColumn('next_tasks', 'description')) {
                    $table->text('description')->nullable()->after('task_title');
                }
                if (!Schema::hasColumn('next_tasks', 'labels')) {
                    $table->string('labels')->nullable()->comment('برچسب‌های رنگی ترلو');
                }
            });
        }

        // ۲. ساخت جدول مستقل پیوست‌های متعدد تسک (Attachments مثل ترلو)
        if (!Schema::hasTable('task_attachments')) {
            Schema::create('task_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('task_id');
                $table->string('file_name');
                $table->string('file_path');
                $table->string('uploaded_by')->comment('client یا staff');
                $table->timestamps();

                $table->foreign('task_id')->references('id')->on('next_tasks')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
        if (Schema::hasTable('next_tasks')) {
            Schema::table('next_tasks', function (Blueprint $table) {
                $table->dropColumn(['description', 'labels']);
            });
        }
    }
};