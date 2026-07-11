<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_logs', function (Blueprint $table) {
            $table->id();
            
            // اتصال به پرونده لید
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            
            // کانال ارتباطی (telegram, whatsapp, instagram, site)
            $table->string('channel')->index();
            
            // فرستنده پیام (user = مشتری, bot = ربات پیشگامان, agent = کارشناس فروش)
            $table->enum('sender_type', ['user', 'bot', 'agent'])->index();
            
            // متن پیام برای پردازش NLP
            $table->text('message');
            
            // 💡 فیلد کلیدی برای فاز ۳: آیا این پیام توسط هوش مصنوعی پردازش و خلاصه شده؟
            $table->boolean('is_analyzed')->default(false);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_logs');
    }
};