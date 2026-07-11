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
    Schema::create('customer_insights', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('customer_id')->index(); // ارتباط با شناسه لید/مشتری پرفکس [cite: 143, 144, 187]
        $table->text('last_activity_summary')->nullable(); // خلاصه چت‌ها و درخواست‌ها [cite: 144]
        $table->string('likely_destination')->nullable(); // مقصد احتمالی مهاجرتی مخاطب [cite: 144]
        $table->string('last_intent')->nullable(); // آخرین هدف یا نیت مشتری (مثلا study_visa) [cite: 187]
        $table->enum('interest_level', ['low', 'medium', 'high'])->default('low'); // سطح علاقه تخمینی [cite: 144]
        $table->float('urgency_score')->default(0.0); // سطح اضطرار متقاضی [cite: 144]
        $table->integer('ai_priority')->default(1); // اولویت پیگیری برای تیم فروش (۱ تا ۵) [cite: 145]
        $table->float('engagement_score')->default(0.0); // میزان درگیری کاربر با محتوا (۰ تا ۱) [cite: 169, 186]
        $table->integer('visit_frequency')->default(0); // تعداد دفعات بازدید کاربر از سایت [cite: 186]
        $table->enum('channel_last_active', ['whatsapp', 'telegram', 'instagram', 'site'])->default('site'); // آخرین کانال فعال [cite: 186]
        $table->json('top_keywords')->nullable(); // کلمات کلیدی پرتکرار استخراج شده [cite: 187]
        $table->text('recommended_action')->nullable(); // پیشنهاد هوش مصنوعی برای اقدام بعدی مشاور [cite: 108, 146]
        $table->dateTime('last_ai_update')->nullable(); // آخرین آپدیت مدل هوش مصنوعی [cite: 146]
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_insights');
    }
};
