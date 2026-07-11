<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // تزریق فیلد نقش (Role)
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('agent')->after('email');
            }
            // تزریق فیلد دسترسی‌های ریز اتمیک
            if (!Schema::hasColumn('users', 'permissions')) {
                $table->text('permissions')->nullable()->after('role');
            }
            // تزریق کلید خارجی دپارتمان
            if (!Schema::hasColumn('users', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('permissions');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'permissions', 'department_id']);
        });
    }
};