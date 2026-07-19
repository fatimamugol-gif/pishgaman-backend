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
        Schema::create('staff_messages', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->unsignedBigInteger('sender_id');
            $blueprint->unsignedBigInteger('receiver_id');
            $blueprint->text('message');
            $blueprint->boolean('is_read')->default(false);
            $blueprint->timestamps();

            // Foreign keys referencing users table
            $blueprint->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $blueprint->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_messages');
    }
};
