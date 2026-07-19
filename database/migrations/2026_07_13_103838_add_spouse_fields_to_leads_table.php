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
        Schema::table('leads', function (Blueprint $table) {
            // 🛡️ گارد کنترل فیزیکی دیتابیس: تنها در صورتی ستون را بساز که از قبل وجود نداشته باشد
            if (!Schema::hasColumn('leads', 'spouse_name')) {
                $table->string('spouse_name')->nullable()->after('name');
            }
            
            if (!Schema::hasColumn('leads', 'spouse_phone')) {
                $table->string('spouse_phone')->nullable()->after('phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // حذف امن فیلدها در صورت بازگشت مایگریشن
            if (Schema::hasColumn('leads', 'spouse_name')) {
                $table->dropColumn('spouse_name');
            }
            if (Schema::hasColumn('leads', 'spouse_phone')) {
                $table->dropColumn('spouse_phone');
            }
        });
    }
};