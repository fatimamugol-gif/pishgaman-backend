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
    // ۱. ساخت جدول فلوها و شروط تخصیص داینامیک
    Schema::create('next_workflows', function (Blueprint $table) {
        $table->id();
        $table->string('title'); // مثال: فلوچارت دپارتمان مهاجرت تحصیلی
        $table->unsignedBigInteger('department_id');
        $table->boolean('is_active')->default(1);
        $table->json('flow_rules'); // ذخیره کل بلاک‌های شرطی، امتیازها و وزن‌های چیده شده در فرانت
        $table->timestamps();
    });

    // ۲. اضافه کردن پیوند دپارتمان به جدول فیزیکی agents جهت مدیریت دپارتمان کارشناسان پرفکس
    Schema::table('agents', function (Blueprint $table) {
        if (!Schema::hasColumn('agents', 'department_id')) {
            $table->unsignedBigInteger('department_id')->nullable()->after('role');
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('next_workflows');
    }
};
