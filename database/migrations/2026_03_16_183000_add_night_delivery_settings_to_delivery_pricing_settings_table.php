<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_pricing_settings', function (Blueprint $table) {
            $table->boolean('night_delivery_enabled')->default(true)->after('surge_enabled');
            $table->decimal('night_delivery_fee', 10, 2)->default(20)->after('night_delivery_enabled');
            $table->time('night_starts_at')->default('22:00:00')->after('night_delivery_fee');
            $table->time('night_ends_at')->default('06:00:00')->after('night_starts_at');
        });

        DB::table('delivery_pricing_settings')->update([
            'night_delivery_enabled' => true,
            'night_delivery_fee' => config('delivery_pricing.night_delivery_fee', 20),
            'night_starts_at' => config('delivery_pricing.night_starts_at', '22:00') . ':00',
            'night_ends_at' => config('delivery_pricing.night_ends_at', '06:00') . ':00',
        ]);
    }

    public function down(): void
    {
        Schema::table('delivery_pricing_settings', function (Blueprint $table) {
            $table->dropColumn([
                'night_delivery_enabled',
                'night_delivery_fee',
                'night_starts_at',
                'night_ends_at',
            ]);
        });
    }
};
