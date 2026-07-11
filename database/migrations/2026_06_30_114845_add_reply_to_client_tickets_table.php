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
        if (Schema::hasTable('client_tickets')) {
            Schema::table('client_tickets', function (Blueprint $table) {
                // 🛡️ گارد امنیتی: تنها در صورتی ستون ساخته شود که از قبل وجود نداشته باشد
                if (!Schema::hasColumn('client_tickets', 'reply')) {
                    $table->text('reply')->nullable()->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('client_tickets')) {
            Schema::table('client_tickets', function (Blueprint $table) {
                // رول‌بک تمیز برای حذف ستون در صورت نیاز
                if (Schema::hasColumn('client_tickets', 'reply')) {
                    $table->dropColumn('reply');
                }
            });
        }
    }
};