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
        // اضافه کردن فیلدهای فیزیکی نام و تلفن به صورت Nullable
        if (!Schema::hasColumn('leads', 'name')) {
            $table->string('name')->nullable()->after('perfex_lead_id');
        }
        if (!Schema::hasColumn('leads', 'phone')) {
            $table->string('phone')->nullable()->after('name');
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
