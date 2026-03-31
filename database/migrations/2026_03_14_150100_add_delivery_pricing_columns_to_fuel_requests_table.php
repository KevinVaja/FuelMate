<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fuel_requests', function (Blueprint $table) {
            $table->decimal('slab_charge', 8, 2)->default(0)->after('delivery_charge');
            $table->decimal('handling_fee', 8, 2)->default(0)->after('slab_charge');
            $table->decimal('night_fee', 8, 2)->default(0)->after('handling_fee');
            $table->decimal('surge_fee', 8, 2)->default(0)->after('night_fee');
            $table->decimal('priority_fee', 8, 2)->default(0)->after('surge_fee');
            $table->decimal('long_distance_fee', 8, 2)->default(0)->after('priority_fee');
            $table->decimal('pump_earning', 10, 2)->default(0)->after('long_distance_fee');
            $table->decimal('platform_earning', 10, 2)->default(0)->after('pump_earning');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_requests', function (Blueprint $table) {
            $table->dropColumn([
                'slab_charge',
                'handling_fee',
                'night_fee',
                'surge_fee',
                'priority_fee',
                'long_distance_fee',
                'pump_earning',
                'platform_earning',
            ]);
        });
    }
};
