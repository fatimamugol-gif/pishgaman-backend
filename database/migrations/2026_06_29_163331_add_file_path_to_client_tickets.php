<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('client_tickets', function (Blueprint $table) {
        // فیلد ذخیره ضمیمه باینری تیکت کلاینت
        $table->string('attachment_path')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_tickets', function (Blueprint $table) {
            //
        });
    }
};
