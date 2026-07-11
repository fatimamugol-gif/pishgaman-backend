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
            // اضافه کردن ستون رابطه با جدول تسک‌ها به صورت Nullable (چون شاید یادآور مستقل هم داشته باشیم)
            if (!Schema::hasColumn('next_reminders', 'task_id')) {
                $table->unsignedBigInteger('task_id')->nullable()->after('lead_id')->index();
                $table->foreign('task_id')->references('id')->on('next_tasks')->onDelete('cascade');
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
