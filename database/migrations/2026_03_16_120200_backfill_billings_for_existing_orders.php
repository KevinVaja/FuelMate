<?php

use App\Models\Billing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GST_PERCENT = 18.0;
    private const PLATFORM_FEE = 10.0;
    private const AGENT_COMMISSION_PERCENT = 3.0;
    private const ADMIN_COMMISSION_PERCENT = 2.0;

    public function up(): void
    {
        if (! Schema::hasTable('billings') || ! Schema::hasTable('fuel_requests')) {
            return;
        }

        foreach (DB::table('fuel_requests')->select([
            'id',
            'status',
            'payment_status',
            'fuel_price_per_liter',
            'quantity_liters',
            'distance_km',
            'booked_distance_km',
            'created_at',
            'updated_at',
        ])->get() as $order) {
            $fuelPrice = $this->normalizeAmount((float) $order->fuel_price_per_liter);
            $fuelQuantity = $this->normalizeAmount((float) $order->quantity_liters);
            $fuelTotal = $this->normalizeAmount($fuelPrice * $fuelQuantity);
            $distance = $this->resolveDistance(
                $order->booked_distance_km !== null ? (float) $order->booked_distance_km : null,
                $order->distance_km !== null ? (float) $order->distance_km : null,
            );
            $deliveryCharge = $this->resolveDeliveryCharge($distance);
            $subtotal = $this->normalizeAmount($fuelTotal + $deliveryCharge + self::PLATFORM_FEE);
            $gstAmount = $this->normalizeAmount($subtotal * (self::GST_PERCENT / 100));
            $totalAmount = $this->normalizeAmount($subtotal + $gstAmount);
            $agentEarning = $this->normalizeAmount($deliveryCharge + ($fuelTotal * (self::AGENT_COMMISSION_PERCENT / 100)));
            $adminCommission = $this->normalizeAmount(self::PLATFORM_FEE + ($fuelTotal * (self::ADMIN_COMMISSION_PERCENT / 100)));
            $billingStatus = $this->resolveBillingStatus((string) $order->status, (string) $order->payment_status);

            DB::table('billings')->updateOrInsert(
                ['order_id' => $order->id],
                [
                    'billing_status' => $billingStatus,
                    'fuel_price_per_liter' => $fuelPrice,
                    'fuel_quantity' => $fuelQuantity,
                    'fuel_total' => $fuelTotal,
                    'delivery_charge' => $deliveryCharge,
                    'platform_fee' => self::PLATFORM_FEE,
                    'gst_percent' => self::GST_PERCENT,
                    'gst_amount' => $gstAmount,
                    'total_amount' => $totalAmount,
                    'agent_commission_percent' => self::AGENT_COMMISSION_PERCENT,
                    'agent_earning' => $agentEarning,
                    'admin_commission' => $adminCommission,
                    'coupon_discount' => null,
                    'surge_pricing_multiplier' => 1,
                    'refundable_amount' => $totalAmount,
                    'refunded_amount' => 0,
                    'refund_status' => Billing::REFUND_NONE,
                    'settlement_status' => Billing::SETTLEMENT_PENDING,
                    'created_at' => $order->created_at ?? now(),
                    'updated_at' => $order->updated_at ?? now(),
                ]
            );

            DB::table('fuel_requests')
                ->where('id', $order->id)
                ->update([
                    'delivery_charge' => $deliveryCharge,
                    'slab_charge' => $deliveryCharge,
                    'handling_fee' => 0,
                    'night_fee' => 0,
                    'surge_fee' => 0,
                    'priority_fee' => 0,
                    'long_distance_fee' => 0,
                    'pump_earning' => $agentEarning,
                    'platform_earning' => $adminCommission,
                    'total_amount' => $totalAmount,
                    'booked_distance_km' => $distance,
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('billings')) {
            return;
        }

        DB::table('billings')->delete();
    }

    private function resolveBillingStatus(string $orderStatus, string $paymentStatus): string
    {
        if ($paymentStatus === 'paid') {
            return Billing::STATUS_PAID;
        }

        return $orderStatus === 'pending'
            ? Billing::STATUS_ESTIMATED
            : Billing::STATUS_FINAL;
    }

    private function resolveDistance(?float $bookedDistanceKm, ?float $distanceKm): float
    {
        if ($bookedDistanceKm !== null && $bookedDistanceKm > 0) {
            return $this->normalizeAmount((float) $bookedDistanceKm);
        }

        if ($distanceKm !== null && $distanceKm > 0) {
            return $this->normalizeAmount((float) $distanceKm);
        }

        return 5.0;
    }

    private function resolveDeliveryCharge(float $distance): float
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

    private function normalizeAmount(float $amount): float
    {
        return round($amount, 2);
    }
};
