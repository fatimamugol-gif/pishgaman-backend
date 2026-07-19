<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('next_tasks', function (Blueprint $table) {
            // ساخت امن فیلدها (اگر از دفعات قبل ناقص اضافه شده باشن، ارور نمیده)
            if (!Schema::hasColumn('next_tasks', 'reminder_at')) {
                $table->timestamp('reminder_at')->nullable()->after('has_reminder');
            }
            
            if (!Schema::hasColumn('next_tasks', 'due_date_at')) {
                $table->timestamp('due_date_at')->nullable()->after('due_date_shamsi');
            }
            
            if (!Schema::hasColumn('next_tasks', 'start_date_at')) {
                $table->timestamp('start_date_at')->nullable()->after('start_date_shamsi');
            }
            
            if (!Schema::hasColumn('next_tasks', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('completed_at_shamsi');
            }
        });

        // اضافه کردن ایندکس با استفاده از یک کوئری خام و امن SQL
        // این کار روی هر ورژن لاراول و دیتابیسی بدون خطا اجرا میشه
        try {
            DB::statement("ALTER TABLE `next_tasks` ADD INDEX `next_tasks_status_index` (`status`)");
        } catch (\Exception $e) {
            // اگر ایندکس از قبل وجود داشت، خطا رو نادیده بگیر و رد شو
        }
    }

    public function down(): void
    {
        Schema::table('next_tasks', function (Blueprint $table) {
            try {
                $table->dropIndex(['status']);
            } catch (\Exception $e) {}
            
            $table->dropColumn(['reminder_at', 'due_date_at', 'start_date_at', 'completed_at']);
        });
    }
};