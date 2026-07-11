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
    Schema::create('next_reminders', function (Blueprint $table) {
        $table->id(); // 💡 علامت $ اضافه شد
        $table->unsignedBigInteger('lead_id'); // متصل به لید چهل‌گانه
        $table->string('title');
        $table->text('description')->nullable();
        $table->string('reminder_date_shamsi'); // تاریخ شمسی یادآور
        $table->string('reminder_time')->nullable(); // ساعت یادآور
        $table->bigInteger('reminder_timestamp')->nullable()->index();
        $table->boolean('is_notified')->default(false); // آیا هشدار داده شده است؟
        $table->timestamps();

        $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('next_reminders');
    }
};
