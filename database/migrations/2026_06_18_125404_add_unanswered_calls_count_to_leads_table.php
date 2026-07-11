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
            Schema::table('leads', function (Blueprint $table) {
                if (!Schema::hasColumn('leads', 'unanswered_calls_count')) {
                    $table->integer('unanswered_calls_count')->default(0)->after('status');
                }
            });
        }

        public function down()
        {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('unanswered_calls_count');
            });
        }
};
