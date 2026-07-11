<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->comment('شناسه کارشناس پاسخ‌دهنده از جدول یوزرز');
            $table->string('sender_type')->default('client')->comment('client یا staff');
            $table->string('sender_name')->nullable();
            $table->text('body');
            $table->string('file_path')->nullable()->comment('ضمیمه اختصاصی این پیام');
            $table->timestamps();

            // اتصال کلید خارجی به جدول اصلی تیکت‌ها برای حذف آبشاری
            $table->foreign('ticket_id')->references('id')->on('client_tickets')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};