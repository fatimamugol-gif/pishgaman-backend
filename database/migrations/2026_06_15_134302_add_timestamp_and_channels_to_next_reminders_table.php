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
        Schema::table('next_reminders', function (Blueprint $table) {
            // ۱. ستون تایم‌استمپ برای کوئری‌های فوق سریع ثانیه‌ای
            if (!Schema::hasColumn('next_reminders', 'reminder_timestamp')) {
                $table->bigInteger('reminder_timestamp')->nullable()->index()->after('reminder_date_shamsi');
            }
            // ۲. ستون جیسون برای ذخیره سکوهای انتخابی کارشناس (sms, telegram, in_app)
            if (!Schema::hasColumn('next_reminders', 'notification_channels')) {
                $table->json('notification_channels')->nullable()->after('reminder_time');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('next_reminders', function (Blueprint $table) {
            //
        });
    }
};
