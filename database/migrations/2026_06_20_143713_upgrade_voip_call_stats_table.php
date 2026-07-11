<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voip_call_stats', function (Blueprint $table) {
            // تضمین وجود ستون‌های تحلیلی پرفورمنس
            if (!Schema::hasColumn('voip_call_stats', 'call_type')) {
                $table->string('call_type')->default('inbound')->after('disposition'); // inbound, outbound
            }
            if (!Schema::hasColumn('voip_call_stats', 'lead_id')) {
                $table->unsignedInteger('lead_id')->nullable()->after('unique_id');
            }
            if (!Schema::hasColumn('voip_call_stats', 'call_date')) {
                $table->date('call_date')->nullable()->after('call_type');
            }
        });
        
        // ارتقای فیزیکی جدول کارشناسان برای نگهداری تفکیکی آمار روزانه
        Schema::table('agents', function (Blueprint $table) {
            if (!Schema::hasColumn('agents', 'daily_outbound_calls')) {
                $table->integer('daily_outbound_calls')->default(0);
                $table->integer('daily_inbound_calls')->default(0);
            }
        });
    }

    public function down(): void
    {
        // اعمال روال برگشت در صورت نیاز
    }
};