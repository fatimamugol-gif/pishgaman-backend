<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shared_documents')) {
            Schema::create('shared_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('lead_id')->comment('آیدی کلاینت در جدول leads_full');
                $table->string('title');
                $table->string('file_path');
                $table->boolean('is_approved_by_staff')->default(1)->comment('تایید کارشناس جهت رویت');
                $table->boolean('is_signed_by_client')->default(0)->comment('آیا کلاینت تایید و امضا کرده؟');
                $table->timestamp('client_signed_at')->nullable()->comment('زمان دقیق امضا و تایید کلاینت');
                $table->string('client_ip')->nullable();
                $table->timestamps();

                $table->foreign('lead_id')->references('id')->on('leads_full')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_documents');
    }
};