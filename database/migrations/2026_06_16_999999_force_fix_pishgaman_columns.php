<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 🚀 تزریق مستقیم ستون فعال/غیرفعال به جدول کاربران قدیمی پرفکس
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'active')) {
                    $table->tinyInteger('active')->default(1);
                }
                if (!Schema::hasColumn('users', 'role')) {
                    $table->string('role')->default('agent');
                }
                if (!Schema::hasColumn('users', 'permissions')) {
                    $table->text('permissions')->nullable();
                }
                if (!Schema::hasColumn('users', 'department_id')) {
                    $table->unsignedBigInteger('department_id')->nullable();
                }
            });
        }

        // 🚀 تزریق ستون پرمیشن الگو به جدول دپارتمان‌ها
        if (Schema::hasTable('next_departments')) {
            Schema::table('next_departments', function (Blueprint $table) {
                if (!Schema::hasColumn('next_departments', 'permissions')) {
                    $table->text('permissions')->nullable();
                }
            });
        }
    }

    public function down()
    {
        // نیازی به رول‌بک در محیط توسعه نیست
    }
};