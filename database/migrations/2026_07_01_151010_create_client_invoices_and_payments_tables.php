<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // ۱. بررسی و ارتقای جدول فاکتورها به ساختار تایم‌استمپ عددی
        if (!Schema::hasTable('client_invoices')) {
            Schema::create('client_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('lead_id');
                $table->string('invoice_number')->unique();
                $table->string('title');
                $table->decimal('amount', 15, 2);
                $table->integer('due_timestamp'); // 🎯 ذخیره سررسید به صورت عددی (Unix Timestamp)
                $table->enum('payment_type', ['full', 'installment'])->default('full');
                $table->enum('status', ['unpaid', 'pending_review', 'paid', 'rejected'])->default('unpaid');
                $table->text('reject_reason')->nullable();
                $table->timestamps();
            });
        } else {
            // 🎯 سناریوی تغییر لایو جدول موجود
            Schema::table('client_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('client_invoices', 'invoice_number')) {
                    $table->string('invoice_number')->nullable()->unique();
                }
                // الحاق ستون عددی تایم‌استمپ سررسید در صورت عدم وجود
                if (!Schema::hasColumn('client_invoices', 'due_timestamp')) {
                    $table->integer('due_timestamp')->nullable();
                }
                if (!Schema::hasColumn('client_invoices', 'payment_type')) {
                    $table->enum('payment_type', ['full', 'installment'])->default('full');
                }
                if (!Schema::hasColumn('client_invoices', 'reject_reason')) {
                    $table->text('reject_reason')->nullable();
                }
            });
        }

        // ۲. ساخت جدول لاگ تراکنش‌ها و فیش‌های بانکی
        if (!Schema::hasTable('invoice_payments')) {
            Schema::create('invoice_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('invoice_id');
                $table->enum('gateway', ['bank_receipt', 'zarinpal', 'nextpay'])->default('bank_receipt');
                $table->string('file_path')->nullable();
                $table->string('authority_token')->nullable();
                $table->string('ref_id')->nullable();
                $table->decimal('amount_paid', 15, 2);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('invoice_payments');
    }
};