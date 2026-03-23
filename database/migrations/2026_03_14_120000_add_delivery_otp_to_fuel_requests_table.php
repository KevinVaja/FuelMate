<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('fuel_requests', function (Blueprint $table) {
            $table->string('delivery_otp', 6)->nullable()->after('distance_km');
            $table->timestamp('delivery_otp_generated_at')->nullable()->after('delivery_otp');
            $table->timestamp('delivery_otp_verified_at')->nullable()->after('delivery_otp_generated_at');
        });
    }

    public function down(): void {
        Schema::table('fuel_requests', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_otp',
                'delivery_otp_generated_at',
                'delivery_otp_verified_at',
            ]);
        });
    }
};
