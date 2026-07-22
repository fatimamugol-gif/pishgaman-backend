<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('next_attendance_clocks', function (Blueprint $table) {
            // افزودن فیلد device_id برای ذخیره آدرس IP دستگاه
            $table->string('device_id')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('next_attendance_clocks', function (Blueprint $table) {
            $table->dropColumn('device_id');
        });
    }
};
