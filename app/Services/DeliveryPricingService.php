<?php

namespace App\Services;

use App\Models\DeliveryPricingSetting;
use App\Models\DeliverySlab;
use Illuminate\Support\Carbon;

class DeliveryPricingService
{
    public function getSlabCharge(float $distance): float
    {
        $slab = DeliverySlab::query()
            ->where('min_km', '<=', $distance)
            ->where('max_km', '>=', $distance)
            ->orderBy('min_km')
            ->first();

        if ($slab) {
            return (float) $slab->charge;
        }

        $fallback = DeliverySlab::query()
            ->orderBy('max_km', 'desc')
            ->value('charge');

        return $fallback !== null
            ? (float) $fallback
            : $this->defaultSlabCharge($distance);
    }

    public function getNightFee(?Carbon $moment = null): float
    {
        $settings = $this->nightDeliverySettings();
        $now = ($moment ?? Carbon::now())->copy();

        if (! $settings['enabled']) {
            return 0.0;
        }

        [$startHour, $startMinute] = $this->explodeTime($settings['starts_at']);
        [$endHour, $endMinute] = $this->explodeTime($settings['ends_at']);

        $nightStart = $now->copy()->setTime($startHour, $startMinute);
        $nightEnd = $now->copy()->setTime($endHour, $endMinute);

        $isNight = $nightStart->lessThan($nightEnd)
            ? $now->betweenIncluded($nightStart, $nightEnd)
            : ($now->greaterThanOrEqualTo($nightStart) || $now->lessThan($nightEnd));

        return $isNight ? $settings['fee'] : 0.0;
    }

    public function nightDeliverySettings(): array
    {
        $settings = DeliveryPricingSetting::query()->first();

        return [
            'enabled' => (bool) ($settings?->night_delivery_enabled ?? config('delivery_pricing.night_delivery_enabled', true)),
            'fee' => round((float) ($settings?->night_delivery_fee ?? config('delivery_pricing.night_delivery_fee', 0)), 2),
            'starts_at' => substr((string) ($settings?->night_starts_at ?? config('delivery_pricing.night_starts_at', '22:00')), 0, 5),
            'ends_at' => substr((string) ($settings?->night_ends_at ?? config('delivery_pricing.night_ends_at', '06:00')), 0, 5),
        ];
    }

    public function updateNightDeliverySettings(array $attributes): DeliveryPricingSetting
    {
        $settings = DeliveryPricingSetting::query()->first() ?? new DeliveryPricingSetting();

        $settings->fill([
            'night_delivery_enabled' => (bool) $attributes['night_delivery_enabled'],
            'night_delivery_fee' => round((float) $attributes['night_delivery_fee'], 2),
            'night_starts_at' => $attributes['night_starts_at'],
            'night_ends_at' => $attributes['night_ends_at'],
        ]);

        $settings->save();

        return $settings->fresh();
    }

    public function getPriorityFee(bool $isPriority): float
    {
        return $isPriority ? (float) config('delivery_pricing.priority_fee', 0) : 0.0;
    }

    public function getLongDistanceFee(float $distance): float
    {
        $threshold = (float) config('delivery_pricing.long_distance_km', 0);

        return $distance > $threshold
            ? (float) config('delivery_pricing.long_distance_fee', 0)
            : 0.0;
    }

    public function calculateFinalCharge(float $distance, bool $isPriority): array
    {
        $slabCharge = $this->getSlabCharge($distance);
        $handlingFee = (float) config('delivery_pricing.handling_fee', 0);
        $nightFee = $this->getNightFee();
        $priorityFee = $this->getPriorityFee($isPriority);
        $longDistanceFee = $this->getLongDistanceFee($distance);

        $final = $slabCharge + $handlingFee + $nightFee + $priorityFee + $longDistanceFee;
        $minCharge = (float) config('delivery_pricing.min_delivery_charge', 0);

        if ($final < $minCharge) {
            $final = $minCharge;
        }

        $pumpPercent = (float) config('delivery_pricing.pump_percent', 0);
        $platformPercent = (float) config('delivery_pricing.platform_percent', 0);

        $pumpEarning = round($final * $pumpPercent / 100, 2);
        $platformEarning = round($final * $platformPercent / 100, 2);

        return [
            'slab_charge' => $slabCharge,
            'handling_fee' => $handlingFee,
            'night_fee' => $nightFee,
            'priority_fee' => $priorityFee,
            'long_distance_fee' => $longDistanceFee,
            'final_delivery_charge' => $final,
            'pump_earning' => $pumpEarning,
            'platform_earning' => $platformEarning,
        ];
    }

    private function explodeTime(string $time): array
    {
        $parts = explode(':', $time);

        return [
            (int) ($parts[0] ?? 0),
            (int) ($parts[1] ?? 0),
        ];
    }

    private function defaultSlabCharge(float $distance): float
    {
        if ($distance <= 3) {
            return 40.0;
        }

        if ($distance <= 6) {
            return 70.0;
        }

        if ($distance <= 10) {
            return 100.0;
        }

        return 150.0;
    }
}
