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
    Schema::table('next_tasks', function (Blueprint $table) {
        // ۱. تفکیک جهت تسک: staff (مخصوص کارشناس) یا client (مخصوص اقدامات کلاینت)
        $table->string('target_audience')->default('staff')->index();
        // ۲. فیلد ذخیره فایل پاسخ کلاینت
        $table->string('client_file_path')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
