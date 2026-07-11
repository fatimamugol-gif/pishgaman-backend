<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade'); // اتصال به پرونده کلاینت
            $table->integer('department_id')->nullable(); // اتصال به دپارتمان‌های مالی، ویزا، پذیرش و...
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('status')->default('open'); // open, answered, closed
            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_tickets');
    }
};