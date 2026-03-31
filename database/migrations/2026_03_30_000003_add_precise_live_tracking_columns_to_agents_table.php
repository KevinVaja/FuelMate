<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->decimal('current_latitude', 10, 8)->nullable()->after('current_lng');
            $table->decimal('current_longitude', 11, 8)->nullable()->after('current_latitude');
            $table->timestamp('last_location_update')->nullable()->after('current_longitude');
        });

        DB::table('agents')
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->update([
                'current_latitude' => DB::raw('current_lat'),
                'current_longitude' => DB::raw('current_lng'),
                'last_location_update' => DB::raw('COALESCE(last_active_at, updated_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'current_latitude',
                'current_longitude',
                'last_location_update',
            ]);
        });
    }
};
