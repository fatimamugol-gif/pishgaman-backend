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
    Schema::table('next_departments', function (Blueprint $table) {
        if (!Schema::hasColumn('next_departments', 'permissions')) {
            $table->text('permissions')->nullable()->after('slug');
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('next_departments', function (Blueprint $table) {
            //
        });
    }
};
