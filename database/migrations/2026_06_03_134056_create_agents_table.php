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
    Schema::create('agents', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('perfex_staff_id')->unique(); // آیدی متناظر مشاور در پرفکس
        $table->string('name');
        $table->string('email')->unique();
        
        // فیلدهای تحلیلی هسته برای تخصیص هوشمند
        $table->boolean('is_active')->default(true); // وضعیت حضور مشاور در شیفت
        $table->integer('max_capacity')->default(10); // سقف لید همزمان
        $table->integer('current_active_leads')->default(0); // تعداد لیدهای فعال فعلی
        $table->decimal('conversion_rate', 5, 2)->default(0.00); // نرخ تبدیل موفق لید به مشتری
        $table->json('specialties')->nullable(); // تخصص‌ها یا دسته‌بندی‌های ترجیحی این مشاور

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
