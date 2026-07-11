<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            // ۱. فیلد امتیاز فیزیکی لید (جهت تفکیک و فیلترینگ بر اساس رنک)
            if (!Schema::hasColumn('leads', 'lead_score')) {
                $table->integer('lead_score')->default(70)->after('phone');
            }

            // ۲. لینک دقیق فرم ورودی وب‌سایت که متقاضی پر کرده است
            if (!Schema::hasColumn('leads', 'web_form_link')) {
                $table->string('web_form_link')->nullable()->after('import_source');
            }

            // ۳. وضعیت فلگ مشاوره عالی ناظر (True / False)
            if (!Schema::hasColumn('leads', 'is_excellent_lead')) {
                $table->boolean('is_excellent_lead')->default(true)->after('web_form_link');
            }
        });
    }

    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['lead_score', 'web_form_link', 'is_excellent_lead']);
        });
    }
};