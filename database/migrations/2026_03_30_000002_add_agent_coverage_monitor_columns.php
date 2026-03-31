<?php

use App\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->enum('status', [
                Agent::STATUS_ACTIVE,
                Agent::STATUS_BUSY,
                Agent::STATUS_OFFLINE,
            ])->default(Agent::STATUS_OFFLINE)->after('is_available');
            $table->timestamp('last_active_at')->nullable()->after('approved_at');
            $table->decimal('pump_latitude', 10, 7)->nullable()->after('current_lng');
            $table->decimal('pump_longitude', 10, 7)->nullable()->after('pump_latitude');
        });

        DB::table('agents')
            ->orderBy('id')
            ->select(['id', 'is_available', 'updated_at', 'current_lat', 'current_lng'])
            ->chunkById(100, function ($agents): void {
                foreach ($agents as $agent) {
                    DB::table('agents')
                        ->where('id', $agent->id)
                        ->update([
                            'status' => $agent->is_available ? Agent::STATUS_ACTIVE : Agent::STATUS_OFFLINE,
                            'last_active_at' => $agent->updated_at,
                            'pump_latitude' => $agent->current_lat,
                            'pump_longitude' => $agent->current_lng,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'last_active_at',
                'pump_latitude',
                'pump_longitude',
            ]);
        });
    }
};
