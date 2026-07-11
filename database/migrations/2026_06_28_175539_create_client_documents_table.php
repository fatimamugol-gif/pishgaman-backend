<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->string('document_type'); // passport, contract, degree, resume, etc.
            $table->string('title'); // عنوان نمایشی فایل
            $table->string('file_path'); // مسیر ذخیره در Storage
            $table->string('status')->default('pending_review'); // pending_review, approved, rejected
            $table->string('uploaded_by')->default('client'); // client یا agent
            $table->text('rejection_reason')->nullable(); // دلیل ریجکت شدن مدرک توسط کارشناس
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_documents');
    }
};