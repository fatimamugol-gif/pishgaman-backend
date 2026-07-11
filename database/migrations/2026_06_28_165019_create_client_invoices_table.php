<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->string('title'); // مثلا: قسط اول قرارداد آوسبیلدونگ
            $table->decimal('amount_toman', 15, 0); // مبلغ به تومان
            $table->string('status')->default('unpaid'); // unpaid, paid, partial, overdue
            $table->timestamp('due_date')->nullable(); // سررسید پرداخت
            $table->timestamp('paid_at')->nullable(); // زمان دقیق پرداخت موفق
            $table->string('tracking_code')->nullable(); // کد پیگیری درگاه یا فیش واریزی
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_invoices');
    }
};