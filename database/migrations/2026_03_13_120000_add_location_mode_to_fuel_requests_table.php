<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('fuel_requests', function (Blueprint $table) {
            $table->string('location_mode', 20)->default('live_gps')->after('delivery_address');
        });
    }

    public function down(): void {
        Schema::table('fuel_requests', function (Blueprint $table) {
            $table->dropColumn('location_mode');
        });
    }
};
