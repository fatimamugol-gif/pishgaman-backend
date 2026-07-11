<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            // ⚡ اضافه کردن ایندکس برای جستجوهای صدم‌ثانیه‌ای در وب‌هوک‌ها
            $table->index('telegram_chat_id');
            $table->index('instagram_sender_id');
            $table->index('phone');
            
            // ایندکس برای مرتب‌سازی سریع لیدها در فیلامنت بر اساس امتیاز فوریت
            $table->index('lead_score'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            //
        });
    }
};
