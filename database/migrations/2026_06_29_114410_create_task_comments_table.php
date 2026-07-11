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
    Schema::create('task_comments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('task_id');
        $table->unsignedBigInteger('user_id'); // نویسنده کامنت (کارشناس یا کلاینت)
        $table->text('comment');
        $table->string('sender_name');
        $table->timestamps();

        // اتصال کلیدهای خارجی برای حفظ یکپارچگی ارجاعات دیتابیس
        $table->foreign('task_id')->references('id')->on('next_tasks')->onDelete('cascade');
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_comments');
    }
};
