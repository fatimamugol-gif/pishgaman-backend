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
            if (!Schema::hasColumn('leads', 'detected_intent')) {
                // 💡 حذف شرط after برای جلوگیری از خطای نبود ستون قبلی
                $table->string('detected_intent')->nullable();
            }
            if (!Schema::hasColumn('leads', 'detected_destination')) {
                $table->string('detected_destination')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            //
        });
    }
};
