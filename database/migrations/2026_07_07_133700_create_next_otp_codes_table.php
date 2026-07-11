<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('next_otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->index();
            $table->string('code');
            $table->string('portal'); // staff یا client
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('next_otp_codes');
    }
};