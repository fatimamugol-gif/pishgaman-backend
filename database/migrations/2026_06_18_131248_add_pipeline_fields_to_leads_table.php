<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'pipeline_stage')) {
                $table->string('pipeline_stage', 50)->default('new')->after('status');
            }
            if (!Schema::hasColumn('leads', 'suspend_reason')) {
                $table->text('suspend_reason')->nullable()->after('pipeline_stage');
            }
        });
    }

    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['pipeline_stage', 'suspend_reason']);
        });
    }
};