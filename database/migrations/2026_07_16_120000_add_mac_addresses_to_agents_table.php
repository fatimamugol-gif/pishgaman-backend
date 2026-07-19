<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // MAC Address fields for device restriction
            $table->string('mac_address_1')->nullable()->after('role');
            $table->string('mac_address_2')->nullable()->after('mac_address_1');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['mac_address_1', 'mac_address_2']);
        });
    }
};
