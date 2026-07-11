<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // ۱. ایجاد جدول فیزیکی دپارتمان‌ها
        if (!Schema::hasTable('next_departments')) {
            Schema::create('next_departments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('permissions')->nullable(); // قالب پرمیشن دپارتمان
                $table->timestamps();
            });
        }

        // ۲. ارتقای بی‌پناه جدول کاربران و لیدها (بدون شروط محدودکننده)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) $table->string('role')->default('agent');
            if (!Schema::hasColumn('users', 'permissions')) $table->text('permissions')->nullable();
            if (!Schema::hasColumn('users', 'department_id')) $table->unsignedBigInteger('department_id')->nullable();
            if (!Schema::hasColumn('users', 'active')) $table->tinyInteger('active')->default(1); // 💡 فیکس ارور فیلد active
        });

        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::dropIfExists('next_departments');
    }
};