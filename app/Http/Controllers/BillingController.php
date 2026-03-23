<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\FuelProduct;
use App\Models\FuelRequest;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillingController extends Controller
{
    public function estimate(Request $request, BillingService $billingService): JsonResponse
    {
        $data = $request->validate([
            'fuel_product_id' => 'required|exists:fuel_products,id',
            'quantity_liters' => 'required|numeric|min:1|max:50',
            'delivery_lat' => 'required|numeric|between:-90,90',
            'delivery_lng' => 'required|numeric|between:-180,180',
            'payment_method' => 'nullable|in:cod,online,wallet,upi,card',
        ]);

        $distanceKm = $billingService->resolveOrderDistance(
            (float) $data['delivery_lat'],
            (float) $data['delivery_lng'],
        );

        if (! $billingService->isDistanceWithinServiceRange($distanceKm)) {
            return response()->json([
                'status' => false,
                'message' => 'Delivery distance must be between 0.1 and 25 km.',
            ], 422);
        }

        $product = FuelProduct::query()->findOrFail($data['fuel_product_id']);

        $order = new FuelRequest([
            'fuel_product_id' => $product->id,
            'quantity_liters' => $data['quantity_liters'],
            'fuel_price_per_liter' => $product->price_per_liter,
            'payment_method' => $billingService->normalizePaymentMethod($data['payment_method'] ?? 'cod'),
            'payment_status' => $billingService->defaultPaymentStatus($data['payment_method'] ?? 'cod'),
        ]);

        $billing = $billingService->generateEstimatedBill($order, $distanceKm);

        return response()->json([
            'status' => true,
            'message' => 'Estimated bill generated successfully.',
            'data' => array_merge($billing, [
                'distance_km' => $distanceKm,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
            ]),
        ]);
    }

    public function invoice(int $id)
    {
        $order = FuelRequest::query()
            ->where('user_id', Auth::id())
            ->with(['billing', 'agent.user', 'user', 'fuelProduct'])
            ->findOrFail($id);

        return view('user.invoice', compact('order'));
    }

    public function adminIndex()
    {
        $billableStatuses = [
            Billing::STATUS_FINAL,
            Billing::STATUS_PAID,
            Billing::STATUS_SETTLED,
        ];

        $summary = [
            'total_revenue_today' => Billing::query()
                ->whereIn('billing_status', $billableStatuses)
                ->whereDate('updated_at', today())
                ->sum('total_amount'),
            'total_gst_collected' => Billing::query()
                ->whereIn('billing_status', [Billing::STATUS_PAID, Billing::STATUS_SETTLED])
                ->sum('gst_amount'),
            'total_platform_fee' => Billing::query()
                ->whereIn('billing_status', $billableStatuses)
                ->sum('platform_fee'),
            'total_agent_payout_pending' => Billing::query()
                ->where('settlement_status', Billing::SETTLEMENT_PENDING)
                ->sum('agent_earning'),
            'total_orders_billed' => Billing::query()
                ->whereIn('billing_status', $billableStatuses)
                ->count(),
            'total_refund_pending' => Billing::query()
                ->where('refund_status', Billing::REFUND_PENDING)
                ->sum('refundable_amount'),
            'total_refunded' => Billing::query()
                ->sum('refunded_amount'),
            'total_cancellation_charges' => FuelRequest::query()
                ->where('is_cancelled', true)
                ->sum('cancellation_charge'),
        ];

        $billings = Billing::query()
            ->with(['order.user', 'order.agent.user', 'order.fuelProduct'])
            ->latest()
            ->paginate(15);

        return view('admin.billing.index', compact('summary', 'billings'));
    }
}
