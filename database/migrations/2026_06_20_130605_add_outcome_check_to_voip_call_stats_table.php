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
        Schema::table('voip_call_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('voip_call_stats', 'is_outcome_submitted')) {
                // صفر یعنی سابمیت نشده، ۱ یعنی سابمیت شده، ۱- یعنی توسط ناظر گزارش شده است
                $table->tinyInteger('is_outcome_submitted')->default(0)->after('disposition');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voip_call_stats', function (Blueprint $table) {
            $table->dropColumn('is_outcome_submitted');
        });
    }
};