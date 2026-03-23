<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\FuelRequest;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderCancellationService
{
    private const CANCELLATION_CHARGE_PAYMENT_METHODS = [
        FuelRequest::CANCELLATION_CHARGE_METHOD_WALLET,
        FuelRequest::CANCELLATION_CHARGE_METHOD_ONLINE,
    ];

    public function __construct(
        private readonly BillingService $billingService,
        private readonly WalletService $agentWalletService,
        private readonly UserWalletService $userWalletService,
        private readonly AdminWalletService $adminWalletService,
    ) {
    }

    public function cancelOrder(FuelRequest $order, string $cancelledBy, string $reason): FuelRequest
    {
        $cancelledBy = strtolower(trim($cancelledBy));
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($order, $cancelledBy, $reason): FuelRequest {
            return $this->cancelLockedOrder(
                $this->lockOrderForCancellation($order),
                $cancelledBy,
                $reason,
            );
        });
    }

    public function payCancellationChargeAndCancel(
        FuelRequest $order,
        string $reason,
        string $paymentMethod,
    ): FuelRequest {
        $reason = trim($reason);
        $paymentMethod = strtolower(trim($paymentMethod));

        if ($reason === '') {
            throw new DomainException('A cancellation reason is required.');
        }

        if (! in_array($paymentMethod, self::CANCELLATION_CHARGE_PAYMENT_METHODS, true)) {
            throw new DomainException('Choose wallet or online to pay the cancellation fee.');
        }

        return DB::transaction(function () use ($order, $reason, $paymentMethod): FuelRequest {
            $lockedOrder = $this->lockOrderForCancellation($order);

            $billing = $this->ensureBillingExists($lockedOrder);
            $breakdown = $this->resolveCancellationBreakdown($lockedOrder, $billing);
            $chargeAmount = $this->normalizeAmount($breakdown['cancellation_charge']);

            if ($lockedOrder->normalizedPaymentMethod() !== 'cod' || $chargeAmount <= 0) {
                return $this->cancelLockedOrder(
                    $lockedOrder,
                    FuelRequest::CANCELLED_BY_CUSTOMER,
                    $reason,
                    $billing,
                    $breakdown,
                );
            }

            $this->guardCancellationPermission($lockedOrder, FuelRequest::CANCELLED_BY_CUSTOMER);
            $this->guardAgainstSuspiciousCancellationPatterns($lockedOrder, FuelRequest::CANCELLED_BY_CUSTOMER);

            $paymentReference = $this->collectCancellationCharge($lockedOrder, $chargeAmount, $paymentMethod);

            $lockedOrder->update([
                'cancellation_charge_payment_status' => FuelRequest::CANCELLATION_CHARGE_PAYMENT_PAID,
                'cancellation_charge_payment_method' => $paymentMethod,
                'cancellation_charge_paid_at' => now(),
                'cancellation_charge_payment_reference' => $paymentReference,
            ]);

            return $this->cancelLockedOrder(
                $lockedOrder->fresh(['billing', 'agent', 'user']),
                FuelRequest::CANCELLED_BY_CUSTOMER,
                $reason,
                $billing->fresh(),
                $breakdown,
            );
        });
    }

    public function approveRefund(FuelRequest $order): FuelRequest
    {
        return DB::transaction(function () use ($order): FuelRequest {
            /** @var FuelRequest $lockedOrder */
            $lockedOrder = FuelRequest::query()
                ->with(['billing', 'user'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $billing = $lockedOrder->billing;

            if (! $billing) {
                throw new DomainException('No billing record exists for this order.');
            }

            if ($billing->refund_status !== Billing::REFUND_PENDING) {
                throw new DomainException('Only pending refunds can be approved.');
            }

            $refundAmount = $this->normalizeAmount((float) $billing->refundable_amount);

            $billing->update([
                'refund_status' => Billing::REFUND_APPROVED,
            ]);

            if ($refundAmount > 0 && $this->requiresWalletRollback($lockedOrder)) {
                $lockedOrder->update([
                    'status' => FuelRequest::STATUS_REFUND_PROCESSING,
                ]);

                $this->processWalletRollback($lockedOrder, $refundAmount);
            }

            if ($refundAmount > 0) {
                $billing = $this->billingService->recordRefund($billing, $refundAmount);
            } else {
                $billing->update([
                    'refund_status' => Billing::REFUND_REFUNDED,
                    'refund_processed_at' => now(),
                    'refundable_amount' => 0,
                ]);
            }

            $lockedOrder->update([
                'status' => FuelRequest::STATUS_CANCELLED,
            ]);

            return $lockedOrder->fresh(['billing', 'user', 'agent']);
        });
    }

    public function autoCancelStalePendingOrders(): int
    {
        $minutes = (int) config('cancellation.auto_cancel.pending_after_minutes', 5);

        if ($minutes <= 0) {
            return 0;
        }

        return $this->autoCancelOrders(
            FuelRequest::query()
                ->where('status', FuelRequest::STATUS_PENDING)
                ->where('is_cancelled', false)
                ->where('created_at', '<=', now()->subMinutes($minutes))
                ->get(),
            FuelRequest::CANCELLED_BY_SYSTEM,
            "Automatically cancelled because no agent accepted the order within {$minutes} minutes."
        );
    }

    public function autoCancelAcceptedOrdersWithoutMovement(): int
    {
        $minutes = (int) config('cancellation.auto_cancel.accepted_without_movement_minutes', 10);

        if ($minutes <= 0) {
            return 0;
        }

        $cutoff = now()->subMinutes($minutes);

        return $this->autoCancelOrders(
            FuelRequest::query()
                ->where('status', FuelRequest::STATUS_ACCEPTED)
                ->where('is_cancelled', false)
                ->where(function ($query) use ($cutoff) {
                    $query->where(function ($inner) use ($cutoff) {
                        $inner->whereNull('agent_last_movement_at')
                            ->where('updated_at', '<=', $cutoff);
                    })->orWhere('agent_last_movement_at', '<=', $cutoff);
                })
                ->get(),
            FuelRequest::CANCELLED_BY_SYSTEM,
            "Automatically cancelled because the assigned agent showed no movement for {$minutes} minutes."
        );
    }

    private function autoCancelOrders(Collection $orders, string $cancelledBy, string $reason): int
    {
        $count = 0;

        foreach ($orders as $order) {
            try {
                $this->cancelOrder($order, $cancelledBy, $reason);
                $count++;
            } catch (DomainException) {
                // Ignore orders that have moved into a new state mid-run.
            }
        }

        return $count;
    }

    private function lockOrderForCancellation(FuelRequest $order): FuelRequest
    {
        /** @var FuelRequest $lockedOrder */
        $lockedOrder = FuelRequest::query()
            ->with(['billing', 'agent', 'user'])
            ->lockForUpdate()
            ->findOrFail($order->id);

        return $lockedOrder;
    }

    private function cancelLockedOrder(
        FuelRequest $lockedOrder,
        string $cancelledBy,
        string $reason,
        ?Billing $billing = null,
        ?array $breakdown = null,
    ): FuelRequest {
        $this->guardCancellationPermission($lockedOrder, $cancelledBy);
        $this->guardAgainstSuspiciousCancellationPatterns($lockedOrder, $cancelledBy);

        $billing ??= $this->ensureBillingExists($lockedOrder);
        $breakdown ??= $this->resolveCancellationBreakdown($lockedOrder, $billing);

        $this->guardCustomerCodCancellationChargePayment($lockedOrder, $cancelledBy, $breakdown['cancellation_charge']);

        $refundPending = $this->requiresRefundApproval($lockedOrder, $breakdown['refundable_amount']);
        $targetStatus = $refundPending
            ? FuelRequest::STATUS_REFUND_PROCESSING
            : FuelRequest::STATUS_CANCELLED;

        $lockedOrder->update([
            'status' => $targetStatus,
            'is_cancelled' => true,
            'cancelled_by' => $cancelledBy,
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
            'cancellation_charge' => $breakdown['cancellation_charge'],
            'delivery_otp' => null,
            'delivery_otp_generated_at' => null,
            'delivery_otp_verified_at' => null,
            'estimated_delivery_minutes' => 0,
            'payment_status' => $this->resolveCancellationPaymentStatus($lockedOrder),
        ]);

        $billing->update([
            'refundable_amount' => $breakdown['refundable_amount'],
            'refund_status' => $this->resolveRefundStatusForCancellation(
                $lockedOrder,
                $breakdown['refundable_amount'],
                $refundPending,
            ),
            'refund_processed_at' => null,
            'agent_earning' => $breakdown['agent_compensation'],
        ]);

        if ($breakdown['agent_compensation'] > 0 && $lockedOrder->agent) {
            $this->processAgentCompensation($lockedOrder, $breakdown['agent_compensation']);
        }

        if (! $refundPending && $breakdown['refundable_amount'] > 0 && $this->requiresWalletRollback($lockedOrder)) {
            $billing->update([
                'refund_status' => Billing::REFUND_APPROVED,
            ]);

            $this->processWalletRollback($lockedOrder, $breakdown['refundable_amount']);
            $this->billingService->recordRefund($billing->fresh(), $breakdown['refundable_amount']);
        }

        return $lockedOrder->fresh(['billing', 'user', 'agent']);
    }

    private function ensureBillingExists(FuelRequest $order): Billing
    {
        if ($order->billing) {
            return $order->billing;
        }

        $distance = $order->booked_distance_km
            ?? $order->distance_km
            ?? 5.0;

        if ($order->status === FuelRequest::STATUS_PENDING) {
            return $this->billingService->createEstimatedBilling($order, (float) $distance);
        }

        return $this->billingService->finalizeBilling($order);
    }

    private function resolveCancellationBreakdown(FuelRequest $order, Billing $billing): array
    {
        $totalAmount = $this->normalizeAmount((float) $billing->total_amount);
        $deliveryCharge = $this->normalizeAmount((float) $billing->delivery_charge);
        $fuelTotal = $this->normalizeAmount((float) $billing->fuel_total);
        $platformFee = $this->normalizeAmount((float) $billing->platform_fee);
        $gstAmount = $this->normalizeAmount((float) $billing->gst_amount);

        return match ($order->status) {
            FuelRequest::STATUS_PENDING => [
                'cancellation_charge' => 0.0,
                'refundable_amount' => $totalAmount,
                'agent_compensation' => 0.0,
            ],
            FuelRequest::STATUS_ACCEPTED,
            FuelRequest::STATUS_FUEL_PREPARING => [
                'cancellation_charge' => $deliveryCharge,
                'refundable_amount' => $this->normalizeAmount(max($totalAmount - $deliveryCharge, 0)),
                'agent_compensation' => $this->normalizeAmount(
                    $deliveryCharge * (float) config('cancellation.charges.agent_compensation_rate', 0.30)
                ),
            ],
            FuelRequest::STATUS_ON_THE_WAY,
            FuelRequest::STATUS_ARRIVED,
            FuelRequest::STATUS_OTP_VERIFICATION => [
                'cancellation_charge' => $this->normalizeAmount($fuelTotal + $deliveryCharge),
                'refundable_amount' => $this->normalizeAmount($platformFee + $gstAmount),
                'agent_compensation' => 0.0,
            ],
            FuelRequest::STATUS_DELIVERED,
            FuelRequest::STATUS_COMPLETED_LEGACY => throw new DomainException('Delivered orders cannot be cancelled.'),
            default => throw new DomainException('This order can no longer be cancelled.'),
        };
    }

    private function guardCancellationPermission(FuelRequest $order, string $cancelledBy): void
    {
        if ($order->is_cancelled || in_array($order->status, [
            FuelRequest::STATUS_CANCELLED,
            FuelRequest::STATUS_REFUND_PROCESSING,
        ], true)) {
            throw new DomainException('This order has already been cancelled.');
        }

        if (in_array($order->status, [
            FuelRequest::STATUS_DELIVERED,
            FuelRequest::STATUS_COMPLETED_LEGACY,
        ], true)) {
            throw new DomainException('Delivered orders cannot be cancelled.');
        }

        match ($cancelledBy) {
            FuelRequest::CANCELLED_BY_CUSTOMER => $this->assertStatusAllowed(
                $order,
                FuelRequest::CUSTOMER_CANCELLABLE_STATUSES,
                'You can only cancel orders before dispatch starts.'
            ),
            FuelRequest::CANCELLED_BY_AGENT => $this->assertStatusAllowed(
                $order,
                FuelRequest::AGENT_CANCELLABLE_STATUSES,
                'Agents can only cancel accepted or fuel preparing orders.'
            ),
            FuelRequest::CANCELLED_BY_ADMIN,
            FuelRequest::CANCELLED_BY_SYSTEM => $this->assertStatusAllowed(
                $order,
                FuelRequest::FORCE_CANCELLABLE_STATUSES,
                'This order cannot be force cancelled at its current stage.'
            ),
            default => throw new DomainException('Invalid cancellation actor provided.'),
        };
    }

    private function assertStatusAllowed(FuelRequest $order, array $allowedStatuses, string $message): void
    {
        if (! in_array($order->status, $allowedStatuses, true)) {
            throw new DomainException($message);
        }
    }

    private function requiresRefundApproval(FuelRequest $order, float $refundableAmount): bool
    {
        return $refundableAmount > 0
            && (bool) config('cancellation.refunds.require_admin_approval', true)
            && $this->requiresWalletRollback($order);
    }

    private function requiresWalletRollback(FuelRequest $order): bool
    {
        return in_array($order->normalizedPaymentMethod(), ['online', 'wallet'], true);
    }

    private function resolveRefundStatusForCancellation(
        FuelRequest $order,
        float $refundableAmount,
        bool $refundPending,
    ): string {
        if ($refundableAmount <= 0 || ! $this->requiresWalletRollback($order)) {
            return Billing::REFUND_NONE;
        }

        return $refundPending
            ? Billing::REFUND_PENDING
            : Billing::REFUND_APPROVED;
    }

    private function resolveCancellationPaymentStatus(FuelRequest $order): string
    {
        if ($order->payment_status === 'paid') {
            return 'paid';
        }

        return $this->requiresWalletRollback($order)
            ? 'failed'
            : (string) $order->payment_status;
    }

    private function guardCustomerCodCancellationChargePayment(
        FuelRequest $order,
        string $cancelledBy,
        float $cancellationCharge,
    ): void {
        if ($cancelledBy !== FuelRequest::CANCELLED_BY_CUSTOMER) {
            return;
        }

        if ($order->normalizedPaymentMethod() !== 'cod') {
            return;
        }

        if ($this->normalizeAmount($cancellationCharge) <= 0) {
            return;
        }

        if ($order->cancellationChargePaymentIsSettled()) {
            return;
        }

        throw new DomainException('Pay the cancellation fee first, then the COD order will be cancelled.');
    }

    private function collectCancellationCharge(
        FuelRequest $order,
        float $chargeAmount,
        string $paymentMethod,
    ): string {
        $chargeAmount = $this->normalizeAmount($chargeAmount);

        if ($chargeAmount <= 0) {
            throw new DomainException('There is no cancellation fee to collect for this order.');
        }

        if ($paymentMethod === FuelRequest::CANCELLATION_CHARGE_METHOD_WALLET) {
            $this->userWalletService->debit($order->user, $chargeAmount);
        }

        $this->adminWalletService->credit($chargeAmount);

        return strtoupper(uniqid('CANCEL-FEE-'));
    }

    private function processWalletRollback(FuelRequest $order, float $refundAmount): void
    {
        $refundAmount = $this->normalizeAmount($refundAmount);

        if ($refundAmount <= 0 || ! $this->requiresWalletRollback($order)) {
            return;
        }

        $this->userWalletService->credit($order->user, $refundAmount);
        $this->adminWalletService->debit($refundAmount);
    }

    private function processAgentCompensation(FuelRequest $order, float $agentCompensation): void
    {
        $agentCompensation = $this->normalizeAmount($agentCompensation);

        if ($agentCompensation <= 0 || ! $order->agent) {
            return;
        }

        $this->agentWalletService->credit($order->agent, $agentCompensation);
        $this->adminWalletService->debit($agentCompensation);
    }

    private function guardAgainstSuspiciousCancellationPatterns(FuelRequest $order, string $cancelledBy): void
    {
        $windowMinutes = (int) config('cancellation.fraud.window_minutes', 1440);

        if ($windowMinutes <= 0) {
            return;
        }

        $windowStart = now()->subMinutes($windowMinutes);

        match ($cancelledBy) {
            FuelRequest::CANCELLED_BY_CUSTOMER => $this->assertCancellationThresholdNotExceeded(
                FuelRequest::query()
                    ->where('user_id', $order->user_id)
                    ->where('cancelled_by', FuelRequest::CANCELLED_BY_CUSTOMER)
                    ->where('is_cancelled', true)
                    ->where('cancelled_at', '>=', $windowStart),
                (int) config('cancellation.fraud.customer_max_cancellations', 3),
                'Too many recent customer cancellations were detected on your account. Please contact support to continue.',
                [
                    'actor' => $cancelledBy,
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                ],
            ),
            FuelRequest::CANCELLED_BY_AGENT => $this->assertCancellationThresholdNotExceeded(
                FuelRequest::query()
                    ->where('agent_id', $order->agent_id)
                    ->where('cancelled_by', FuelRequest::CANCELLED_BY_AGENT)
                    ->where('is_cancelled', true)
                    ->where('cancelled_at', '>=', $windowStart),
                (int) config('cancellation.fraud.agent_max_cancellations', 5),
                'Too many recent agent-side cancellations were detected. Please contact admin support.',
                [
                    'actor' => $cancelledBy,
                    'agent_id' => $order->agent_id,
                    'order_id' => $order->id,
                ],
            ),
            default => null,
        };
    }

    private function assertCancellationThresholdNotExceeded(
        $query,
        int $threshold,
        string $message,
        array $context,
    ): void {
        if ($threshold <= 0) {
            return;
        }

        $recentCancellations = (int) $query->count();

        if ($recentCancellations < $threshold) {
            return;
        }

        logger()->warning('FuelMate cancellation fraud protection triggered.', array_merge($context, [
            'recent_cancellations' => $recentCancellations,
            'threshold' => $threshold,
        ]));

        throw new DomainException($message);
    }

    private function normalizeAmount(float $amount): float
    {
        return round($amount, 2);
    }
}
