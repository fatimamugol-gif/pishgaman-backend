<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_bases', function (Blueprint $table) {
            $table->id();
            
            // عنوان قانون یا محتوا (مثلاً: "شرایط ویزای تحصیلی کانادا 2026")
            $table->string('title');
            
            // دسته‌بندی (مثلاً: FAQ, Rules, Contract, General)
            $table->string('category')->default('general');
            
            // متن کامل و طولانی مقاله/قانون
            $table->longText('content');
            
            // آیا این قانون هنوز معتبر است و باید در جستجوی هوش مصنوعی لحاظ شود؟
            $table->boolean('is_active')->default(true);
            
            // 💡 آیدی متناظر در دیتابیس برداری (بعداً در مرحله Embeddings پر می‌شود)
            $table->string('vector_id')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_bases');
    }
};