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
        Schema::table('agents', function (Blueprint $table) {
            if (!Schema::hasColumn('agents', 'working_days')) {
                $table->json('working_days')->nullable()->after('specialties');
            }
            if (!Schema::hasColumn('agents', 'allowed_sources')) {
                $table->json('allowed_sources')->nullable()->after('working_days');
            }
            if (!Schema::hasColumn('agents', 'is_emergency')) {
                $table->boolean('is_emergency')->default(0)->after('is_active');
            }
            if (!Schema::hasColumn('agents', 'department_id')) {
                $table->integer('department_id')->default(1)->after('id');
            }
            if (!Schema::hasColumn('agents', 'role')) {
                $table->string('role')->default('call_center')->after('department_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['working_days', 'allowed_sources', 'is_emergency', 'department_id', 'role']);
        });
    }
};