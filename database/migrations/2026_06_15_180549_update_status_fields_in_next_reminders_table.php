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
        Schema::table('next_reminders', function (Blueprint $table) {
            if (!Schema::hasColumn('next_reminders', 'status')) {
                // وضعیت یادآور: در انتظار، انجام شده، ناموفق
                $table->string('status')->default('pending')->after('is_notified'); 
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('next_reminders', function (Blueprint $table) {
            //
        });
    }
};
