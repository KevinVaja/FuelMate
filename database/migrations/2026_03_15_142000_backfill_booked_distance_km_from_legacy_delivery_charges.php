<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fuel_requests')
            || ! Schema::hasColumn('fuel_requests', 'booked_distance_km')
            || ! Schema::hasTable('delivery_charges')) {
            return;
        }

        $chargeBands = DB::table('delivery_charges')
            ->select('min_distance_km', 'max_distance_km', 'charge_amount')
            ->orderBy('min_distance_km')
            ->get();

        if ($chargeBands->isEmpty()) {
            return;
        }

        DB::table('fuel_requests')
            ->select([
                'id',
                'slab_charge',
                'delivery_charge',
                'handling_fee',
                'night_fee',
                'surge_fee',
                'priority_fee',
                'long_distance_fee',
            ])
            ->whereNull('booked_distance_km')
            ->orderBy('id')
            ->chunkById(200, function (Collection $orders) use ($chargeBands): void {
                foreach ($orders as $order) {
                    $charge = (float) ($order->slab_charge ?? 0);

                    if ($charge <= 0) {
                        $charge = (float) ($order->delivery_charge ?? 0)
                            - (float) ($order->handling_fee ?? 0)
                            - (float) ($order->night_fee ?? 0)
                            - (float) ($order->surge_fee ?? 0)
                            - (float) ($order->priority_fee ?? 0)
                            - (float) ($order->long_distance_fee ?? 0);
                    }

                    if ($charge <= 0) {
                        continue;
                    }

                    $chargeBand = $chargeBands->first(function (object $candidate) use ($charge): bool {
                        return abs((float) $candidate->charge_amount - $charge) < 0.01;
                    });

                    if ($chargeBand === null) {
                        continue;
                    }

                    $distanceKm = round((((float) $chargeBand->min_distance_km) + ((float) $chargeBand->max_distance_km)) / 2, 2);

                    DB::table('fuel_requests')
                        ->where('id', $order->id)
                        ->update([
                            'booked_distance_km' => $distanceKm,
                        ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        // Data backfill only. Keep persisted distances intact on rollback.
    }
};
