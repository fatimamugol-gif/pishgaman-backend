<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_bases', function (Blueprint $table) {
            // 💡 تبدیل ستون متن به حالت nullable و همچنین اضافه کردن ستون file_path در صورتی که قبلاً اضافه نشده باشد
            $table->longText('content')->nullable()->change();
            
            if (!Schema::hasColumn('knowledge_bases', 'file_path')) {
                $table->string('file_path')->nullable()->after('content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_bases', function (Blueprint $table) {
            $table->longText('content')->nullable(false)->change();
            $table->dropColumn('file_path');
        });
    }
};