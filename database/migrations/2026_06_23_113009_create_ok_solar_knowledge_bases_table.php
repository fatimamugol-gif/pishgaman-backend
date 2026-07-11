<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ok_solar_knowledge_bases', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // عنوان متنی سند (مثلاً: کاتالوگ پنل ۵۰۰ وات)
            $table->string('category', 50)->nullable()->index(); // دسته‌بندی: panel, inverter, battery, general
            $table->longText('content'); // متن مرجع علمی و فنی تجهیزات خورشیدی
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ok_solar_knowledge_bases');
    }
};