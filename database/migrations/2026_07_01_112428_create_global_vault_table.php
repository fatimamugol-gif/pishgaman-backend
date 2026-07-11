<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('global_vault')) {
            Schema::create('global_vault', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('file_path');
                $table->timestamps();
            });
        }

        // افزودن ستون واسط به جدول تسک‌ها برای اتصال به سند عمومی
        if (Schema::hasTable('next_tasks')) {
            Schema::table('next_tasks', function (Blueprint $table) {
                if (!Schema::hasColumn('next_tasks', 'global_doc_id')) {
                    $table->unsignedBigInteger('global_doc_id')->nullable()->after('lead_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('global_vault');
    }
};