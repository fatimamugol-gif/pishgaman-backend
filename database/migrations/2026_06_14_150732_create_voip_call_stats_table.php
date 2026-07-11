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
        Schema::create('voip_call_stats', function (Blueprint $table) {
            $table->id();
            $table->string('unique_id')->unique(); // آیدی منحصربه‌فرد تماس در استریسک برای جلوگیری از ثبت تکراری
            $table->string('agent_extension')->index(); // داخلی اپراتور (مثلا 102)
            $table->string('customer_phone')->index(); // شماره متقاضی
            $table->string('call_type'); // inbound (ورودی) | outbound (خروجی)
            $table->integer('duration_seconds')->default(0); // مدت مکالمه واقعی
            $table->string('disposition')->default('NO ANSWER'); // ANSWERED | BUSY | NO ANSWER
            $table->timestamp('call_date'); // زمان دقیق برقرار تماس
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voip_call_stats');
    }
};
