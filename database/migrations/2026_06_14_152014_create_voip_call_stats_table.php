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
        // جلوگیری از تداخل در صورت وجود داشتن جدول از قبل
        if (!Schema::hasTable('voip_call_stats')) {
            Schema::create('voip_call_stats', function (Blueprint $table) {
                $table->id();
                $table->string('unique_id')->unique(); // شناسه منحصربه‌فرد تماس در استریسک برای پایش ۳۶۰ درجه
                $table->string('agent_extension')->index(); // داخلی تعریف شده اپراتور (مثلا 102)
                $table->string('customer_phone')->index(); // شماره متقاضی
                $table->integer('duration_seconds')->default(0); // مدت مکالمه واقعی به ثانیه
                $table->string('disposition')->default('NO ANSWER'); // ANSWERED | BUSY | NO ANSWER
                $table->string('call_type')->default('inbound'); // inbound (ورودی) | outbound (خروجی)
                $table->timestamp('call_date')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voip_call_stats');
    }
};