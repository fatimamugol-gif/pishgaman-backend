<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('next_payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('month_shamsi'); // مثل: "1405/04"
            $table->unsignedBigInteger('base_salary'); // حقوق پایه به ریال/تومان
            $table->integer('total_worked_seconds')->default(0); // تجمیع لایو کارکرد ثانیه‌ای از ماژول تردد
            
            // 🎯 گارد پویای کارانه (Performance Bonus) - رزرو شده برای قوانین دپارتمانی آینده
            $table->unsignedBigInteger('performance_bonus')->default(0); 
            
            $table->unsignedBigInteger('deductions')->default(0); // کسورات (تاخیر، غیبت، مساعده)
            $table->unsignedBigInteger('insurance_tax')->default(0); // حق بیمه و مالیات مصوب
            $table->unsignedBigInteger('final_payable'); // خالص دریافتی قطعی کارمند
            $table->enum('status', ['pending', 'approved', 'paid'])->default('pending');
            $table->text('accounting_note')->nullable(); // یادداشت حسابداری
            $table->bigInteger('paid_at_timestamp')->nullable(); // تایم‌استمپ دقیق پرداخت مالی
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_payrolls');
    }
};