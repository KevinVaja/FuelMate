<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fuel_requests') || Schema::hasColumn('fuel_requests', 'booked_distance_km')) {
            return;
        }

        Schema::table('fuel_requests', function (Blueprint $table) {
            $table->decimal('booked_distance_km', 8, 2)->nullable();
        });

        DB::table('fuel_requests')
            ->whereNull('booked_distance_km')
            ->whereNotNull('distance_km')
            ->where('distance_km', '>', 0.1)
            ->update([
                'booked_distance_km' => DB::raw('distance_km'),
            ]);

        $this->backfillUsingDeliverySlabs();
    }

    public function down(): void
    {
        if (! Schema::hasTable('fuel_requests') || ! Schema::hasColumn('fuel_requests', 'booked_distance_km')) {
            return;
        }

        Schema::table('fuel_requests', function (Blueprint $table) {
            $table->dropColumn('booked_distance_km');
        });
    }

    private function backfillUsingDeliverySlabs(): void
    {
        if (! Schema::hasTable('delivery_slabs') || ! Schema::hasColumn('fuel_requests', 'slab_charge')) {
            return;
        }

        $slabs = DB::table('delivery_slabs')
            ->select('min_km', 'max_km', 'charge')
            ->orderBy('min_km')
            ->get();

        if ($slabs->isEmpty()) {
            return;
        }

        DB::table('fuel_requests')
            ->select('id', 'slab_charge')
            ->whereNull('booked_distance_km')
            ->orderBy('id')
            ->chunkById(200, function (Collection $orders) use ($slabs): void {
                foreach ($orders as $order) {
                    $distanceKm = $this->inferDistanceFromSlab((float) ($order->slab_charge ?? 0), $slabs);

                    if ($distanceKm === null) {
                        continue;
                    }

                    DB::table('fuel_requests')
                        ->where('id', $order->id)
                        ->update([
                            'booked_distance_km' => $distanceKm,
                        ]);
                }
            }, 'id');
    }

    private function inferDistanceFromSlab(float $slabCharge, Collection $slabs): ?float
    {
        if ($slabCharge <= 0) {
            return null;
        }

        $slab = $slabs->first(function (object $candidate) use ($slabCharge): bool {
            return abs((float) $candidate->charge - $slabCharge) < 0.01;
        });

        if ($slab === null) {
            return null;
        }

        return round((((float) $slab->min_km) + ((float) $slab->max_km)) / 2, 2);
    }
};
