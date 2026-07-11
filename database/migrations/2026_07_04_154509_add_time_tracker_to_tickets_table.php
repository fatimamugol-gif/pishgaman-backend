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
        Schema::table('tickets', function (Blueprint $table) {
            // مدت زمان صرف شده به دقیقه (پیش‌فرض ۵ دقیقه طبق دستور شما)
            $table->integer('spent_time_minutes')->default(5); 
            $table->unsignedBigInteger('assigned_agent_id')->nullable(); // شناسه مشاور مسئول
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            //
        });
    }
};
