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
        Schema::table('next_tasks', function (Blueprint $table) {
            // 🎯 تبدیل نوع فیلد به string ساده جهت پذیرش وضعیت‌های داینامیک ناظر هوشمند
            $table->string('status', 50)->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('next_tasks', function (Blueprint $table) {
            $table->string('status', 10)->default('pending')->change();
        });
    }
};