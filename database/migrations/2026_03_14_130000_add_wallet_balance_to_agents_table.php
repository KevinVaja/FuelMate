<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->decimal('wallet_balance', 10, 2)->default(0)->after('total_deliveries');
        });

        $completedEarnings = DB::table('fuel_requests')
            ->select('agent_id', DB::raw('SUM(delivery_charge) as total_credited'))
            ->whereNotNull('agent_id')
            ->where('status', 'completed')
            ->groupBy('agent_id')
            ->get();

        foreach ($completedEarnings as $earning) {
            DB::table('agents')
                ->where('id', $earning->agent_id)
                ->update([
                    'wallet_balance' => $earning->total_credited,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('wallet_balance');
        });
    }
};
