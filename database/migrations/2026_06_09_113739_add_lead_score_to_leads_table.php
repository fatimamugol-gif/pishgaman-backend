<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLeadScoreToLeadsTable extends Migration
{
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            // 💡 اصلاح: حذف متد after برای جلوگیری از بهانه‌گیری دیتابیس درباره فیلدهای قبلی
            $table->integer('lead_score')->default(0);
        });
    }

    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('lead_score');
        });
    }
}