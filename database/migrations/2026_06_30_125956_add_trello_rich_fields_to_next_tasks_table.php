<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('next_tasks')) {
            Schema::table('next_tasks', function (Blueprint $table) {
                if (!Schema::hasColumn('next_tasks', 'start_date_shamsi')) {
                    $table->string('start_date_shamsi')->nullable()->comment('تاریخ شروع');
                }
                if (!Schema::hasColumn('next_tasks', 'completed_at_shamsi')) {
                    $table->string('completed_at_shamsi')->nullable()->comment('تاریخ واقعی انجام کار');
                }
                if (!Schema::hasColumn('next_tasks', 'has_reminder')) {
                    $table->boolean('has_reminder')->default(0)->comment('آیا یادآور فعال است؟');
                }
                if (!Schema::hasColumn('next_tasks', 'reminder_time_shamsi')) {
                    $table->string('reminder_time_shamsi')->nullable()->comment('زمان دقیق آلارم یادآور');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('next_tasks')) {
            Schema::table('next_tasks', function (Blueprint $table) {
                $table->dropColumn(['start_date_shamsi', 'completed_at_shamsi', 'has_reminder', 'reminder_time_shamsi']);
            });
        }
    }
};