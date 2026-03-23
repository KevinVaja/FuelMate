<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Billing;
use App\Models\FuelRequest;
use App\Support\DeliveryMetrics;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BillingService
{
    private const GST_PERCENT = 18.0;
    private const PLATFORM_FEE = 10.0;
    private const AGENT_COMMISSION_PERCENT = 3.0;
    private const ADMIN_COMMISSION_PERCENT = 2.0;
    private const DEFAULT_SURGE_MULTIPLIER = 1.0;
    private const DEFAULT_DISTANCE_KM = 5.0;
    private const MAX_DISTANCE_KM = 25.0;

    public function __construct(
        private readonly WalletService $walletService,
        private readonly AdminWalletService $adminWalletService,
        private readonly DeliveryPricingService $deliveryPricingService,
    ) {
    }

    public function generateEstimatedBill(FuelRequest $order, float $distance): array
    {
        return $this->buildBillPayload($order, $distance);
    }

    public function generateFinalBill(FuelRequest $order): array
    {
        return $this->buildBillPayload($order, $this->resolveLockedDistance($order));
    }

    public function createEstimatedBilling(FuelRequest $order, float $distance): Billing
    {
        $bill = $this->generateEstimatedBill($order, $distance);

        $this->syncOrderBillingSnapshot($order, $bill, $distance);

        return $this->persistBilling($order, array_merge($bill, [
            'billing_status' => Billing::STATUS_ESTIMATED,
            'refund_status' => Billing::REFUND_NONE,
            'settlement_status' => Billing::SETTLEMENT_PENDING,
            'refundable_amount' => $bill['total_amount'],
        ]));
    }

    public function finalizeBilling(FuelRequest $order): Billing
    {
        $bill = $this->generateFinalBill($order);
        $distance = $this->resolveLockedDistance($order);

        $this->syncOrderBillingSnapshot($order, $bill, $distance);

        return $this->persistBilling($order, array_merge($bill, [
            'billing_status' => $order->payment_status === 'paid'
                ? Billing::STATUS_PAID
                : Billing::STATUS_FINAL,
            'refund_status' => Billing::REFUND_NONE,
            'settlement_status' => Billing::SETTLEMENT_PENDING,
            'refundable_amount' => $bill['total_amount'],
        ]));
    }

    public function settleDeliveredOrder(FuelRequest $order): Billing
    {
        $billing = $order->relationLoaded('billing')
            ? $order->billing
            : $order->billing()->first();

        if (! $billing) {
            $billing = $this->finalizeBilling($order->fresh());
        }

        $paymentStatus = $order->payment_status;

        if ($this->normalizePaymentMethod((string) $order->payment_method) === 'cod') {
            $paymentStatus = 'paid';
            $order->forceFill(['payment_status' => 'paid'])->save();
        }

        if ($order->agent) {
            $this->walletService->credit($order->agent, (float) $billing->agent_earning);
        }

        $this->adminWalletService->credit((float) $billing->admin_commission);

        $billing->update([
            'billing_status' => $paymentStatus === 'paid'
                ? Billing::STATUS_PAID
                : Billing::STATUS_FINAL,
            'settlement_status' => Billing::SETTLEMENT_PENDING,
            'refundable_amount' => (float) $billing->refundable_amount > 0
                ? $billing->refundable_amount
                : $billing->total_amount,
            'refund_status' => $billing->refund_status ?: Billing::REFUND_NONE,
        ]);

        return $billing->fresh();
    }

    public function markPaymentCaptured(
        FuelRequest $order,
        ?string $paymentReference = null,
        ?string $paymentGateway = null,
        array $webhookPayload = [],
    ): Billing {
        $billing = $order->billing ?: $this->finalizeBilling($order->fresh());

        $order->forceFill(['payment_status' => 'paid'])->save();

        $billing->update([
            'billing_status' => Billing::STATUS_PAID,
            'payment_reference' => $paymentReference,
            'payment_gateway' => $paymentGateway,
            'payment_webhook_payload' => $webhookPayload !== [] ? $webhookPayload : null,
        ]);

        return $billing->fresh();
    }

    public function recordRefund(Billing $billing, float $amount): Billing
    {
        $amount = $this->normalizeAmount($amount);

        if ($amount <= 0) {
            return $billing->fresh() ?? $billing;
        }

        return DB::transaction(function () use ($billing, $amount): Billing {
            $lockedBilling = Billing::query()->lockForUpdate()->findOrFail($billing->id);
            $remainingRefundable = max((float) $lockedBilling->refundable_amount, 0);
            $refundAmount = min($amount, $remainingRefundable);
            $newRemaining = $this->normalizeAmount($remainingRefundable - $refundAmount);
            $newRefunded = $this->normalizeAmount((float) $lockedBilling->refunded_amount + $refundAmount);

            $lockedBilling->update([
                'refundable_amount' => $newRemaining,
                'refunded_amount' => $newRefunded,
                'refund_status' => $newRemaining > 0
                    ? Billing::REFUND_PENDING
                    : Billing::REFUND_REFUNDED,
                'refund_processed_at' => $newRemaining > 0 ? null : now(),
            ]);

            return $lockedBilling->fresh();
        });
    }

    public function normalizePaymentMethod(string $paymentMethod): string
    {
        return match (strtolower($paymentMethod)) {
            'upi', 'card' => 'online',
            default => strtolower($paymentMethod),
        };
    }

    public function defaultPaymentStatus(string $paymentMethod): string
    {
        return $this->normalizePaymentMethod($paymentMethod) === 'wallet'
            ? 'paid'
            : 'pending';
    }

    public function resolveOrderDistance(float $deliveryLat, float $deliveryLng): float
    {
        $nearestAgent = Agent::query()
            ->approvedForOperations()
            ->where('is_available', true)
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->whereDoesntHave('fuelRequests', function ($query) {
                $query->whereIn('status', ['accepted', 'on_the_way', 'delivered']);
            })
            ->get()
            ->sortBy(function (Agent $agent) use ($deliveryLat, $deliveryLng) {
                return DeliveryMetrics::distanceKm(
                    $agent->current_lat,
                    $agent->current_lng,
                    $deliveryLat,
                    $deliveryLng,
                ) ?? PHP_FLOAT_MAX;
            })
            ->first();

        $distanceKm = DeliveryMetrics::distanceKm(
            $nearestAgent?->current_lat,
            $nearestAgent?->current_lng,
            $deliveryLat,
            $deliveryLng,
        ) ?? self::DEFAULT_DISTANCE_KM;

        return $this->normalizeAmount($distanceKm);
    }

    public function isDistanceWithinServiceRange(float $distance): bool
    {
        return $distance > 0 && $distance <= self::MAX_DISTANCE_KM;
    }

    private function buildBillPayload(FuelRequest $order, float $distance): array
    {
        $fuelPrice = $this->normalizeAmount((float) $order->fuel_price_per_liter);
        $fuelQuantity = $this->normalizeAmount((float) $order->quantity_liters);
        $fuelTotal = $this->normalizeAmount($fuelPrice * $fuelQuantity);
        $slabCharge = $this->normalizeAmount($this->deliveryPricingService->getSlabCharge($distance));
        $nightFee = $this->normalizeAmount($this->deliveryPricingService->getNightFee());
        $deliveryCharge = $this->normalizeAmount($slabCharge + $nightFee);
        $platformFee = self::PLATFORM_FEE;
        $couponDiscount = null;
        $surgeMultiplier = self::DEFAULT_SURGE_MULTIPLIER;
        $subtotal = $this->normalizeAmount($fuelTotal + $deliveryCharge + $platformFee);
        $gstAmount = $this->normalizeAmount($subtotal * (self::GST_PERCENT / 100));
        $totalAmount = $this->normalizeAmount($subtotal + $gstAmount);
        $agentEarning = $this->normalizeAmount($deliveryCharge + ($fuelTotal * (self::AGENT_COMMISSION_PERCENT / 100)));
        $adminCommission = $this->normalizeAmount($platformFee + ($fuelTotal * (self::ADMIN_COMMISSION_PERCENT / 100)));

        return [
            'fuel_price_per_liter' => $fuelPrice,
            'fuel_quantity' => $fuelQuantity,
            'fuel_total' => $fuelTotal,
            'slab_charge' => $slabCharge,
            'night_fee' => $nightFee,
            'delivery_charge' => $deliveryCharge,
            'platform_fee' => $platformFee,
            'gst_percent' => self::GST_PERCENT,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'agent_commission_percent' => self::AGENT_COMMISSION_PERCENT,
            'agent_earning' => $agentEarning,
            'admin_commission' => $adminCommission,
            'coupon_discount' => $couponDiscount,
            'surge_pricing_multiplier' => $surgeMultiplier,
        ];
    }

    private function persistBilling(FuelRequest $order, array $payload): Billing
    {
        return Billing::query()->updateOrCreate(
            ['order_id' => $order->id],
            Arr::except($payload, ['slab_charge', 'night_fee'])
        );
    }

    private function syncOrderBillingSnapshot(FuelRequest $order, array $bill, float $distance): FuelRequest
    {
        $order->forceFill([
            'fuel_price_per_liter' => $bill['fuel_price_per_liter'],
            'delivery_charge' => $bill['delivery_charge'],
            'slab_charge' => $bill['slab_charge'],
            'handling_fee' => 0,
            'night_fee' => $bill['night_fee'],
            'surge_fee' => 0,
            'priority_fee' => 0,
            'long_distance_fee' => 0,
            'pump_earning' => $bill['agent_earning'],
            'platform_earning' => $bill['admin_commission'],
            'total_amount' => $bill['total_amount'],
            'distance_km' => $distance,
            'booked_distance_km' => $distance,
        ])->save();

        return $order->fresh();
    }

    private function resolveLockedDistance(FuelRequest $order): float
    {
        if ($order->booked_distance_km !== null && (float) $order->booked_distance_km > 0) {
            return $this->normalizeAmount((float) $order->booked_distance_km);
        }

        if ($order->distance_km !== null && (float) $order->distance_km > 0) {
            return $this->normalizeAmount((float) $order->distance_km);
        }

        return self::DEFAULT_DISTANCE_KM;
    }

    private function normalizeAmount(float $amount): float
    {
        return round($amount, 2);
    }
}
