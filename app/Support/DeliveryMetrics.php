<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\FuelRequest;

class DeliveryMetrics
{
    private const EARTH_RADIUS_KM = 6371;
    private const AVG_SPEED_KMH = 28;
    private const PREPARATION_BUFFER_MINUTES = 8;
    private const EN_ROUTE_BUFFER_MINUTES = 2;

    public static function distanceKm(
        ?float $fromLat,
        ?float $fromLng,
        ?float $toLat,
        ?float $toLng,
    ): ?float {
        if ($fromLat === null || $fromLng === null || $toLat === null || $toLng === null) {
            return null;
        }

        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $originLat = deg2rad($fromLat);
        $destinationLat = deg2rad($toLat);

        $a = sin($latDelta / 2) ** 2
            + cos($originLat) * cos($destinationLat) * sin($lngDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round(self::EARTH_RADIUS_KM * $c, 2);
    }

    public static function estimateMinutes(?float $distanceKm, string $status = 'accepted'): ?int
    {
        if ($distanceKm === null) {
            return null;
        }

        $buffer = in_array($status, ['on_the_way', 'arrived', 'otp_verification'], true)
            ? self::EN_ROUTE_BUFFER_MINUTES
            : self::PREPARATION_BUFFER_MINUTES;

        $travelMinutes = (int) ceil(($distanceKm / self::AVG_SPEED_KMH) * 60);

        return max($buffer + 1, $travelMinutes + $buffer);
    }

    public static function syncOrder(FuelRequest $order, ?Agent $agent = null): FuelRequest
    {
        $agent ??= $order->agent;

        if (! $agent) {
            return $order;
        }

        $distanceKm = self::distanceKm(
            $agent->current_lat,
            $agent->current_lng,
            $order->delivery_lat,
            $order->delivery_lng,
        );

        if ($distanceKm === null) {
            return $order;
        }

        $order->forceFill([
            'distance_km' => $distanceKm,
            'estimated_delivery_minutes' => self::estimateMinutes($distanceKm, $order->status),
        ])->save();

        return $order->refresh();
    }
}
