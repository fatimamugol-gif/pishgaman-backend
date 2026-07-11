<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('client_invoices')) {
            Schema::table('client_invoices', function (Blueprint $table) {
                // 🎯 فیکس نهایی خطای 1054: اگر ستون amount وجود ندارد، آن را با فرمت استاندارد مالی بساز
                if (!Schema::hasColumn('client_invoices', 'amount')) {
                    $table->decimal('amount', 15, 2)->after('title');
                }
                
                if (!Schema::hasColumn('client_invoices', 'invoice_number')) {
                    $table->string('invoice_number')->nullable()->unique();
                }
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
    }

    public function down()
    {
        // رول‌بک صامت
    }
};