<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::table('next_session_reports', function (Blueprint $table) {
        $table->text('delay_reason')->nullable()->after('recommended_plans');
    });
}

public function down()
{
    Schema::table('next_session_reports', function (Blueprint $table) {
        $table->dropColumn('delay_reason');
    });
}
};
