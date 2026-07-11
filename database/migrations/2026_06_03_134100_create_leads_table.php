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
    Schema::create('leads', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('perfex_lead_id')->unique(); // آیدی متناظر لید در پرفکس
        
        // ارتباط لید با مشاور (در ابتدا می‌تواند null باشد تا مغز سیستم آن را تخصیص دهد)
        $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
        
        // فیلدهای تحلیلی و رفتاری
        $table->integer('ai_score')->default(0); // امتیازی که سیستم به کیفیت لید می‌دهد
        $table->string('status')->default('new'); // وضعیت لید در هسته (مثلاً: new, processing, assigned)
        $table->string('source')->nullable(); // منبع ورودی لید
        $table->json('behavioral_data')->nullable(); // ذخیره کلیک‌ها، رفتارها و سیگنال‌های دریافتی

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
