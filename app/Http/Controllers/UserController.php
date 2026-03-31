<?php
namespace App\Http\Controllers;

use App\Models\FuelProduct;
use App\Models\FuelRequest;
use App\Models\SupportTicket;
use App\Services\BillingService;
use App\Services\EmailOtpService;
use App\Services\UserWalletService;
use App\Support\DeliveryMetrics;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserController extends Controller {
    public function dashboard() {
        $user = Auth::user();
        $totalOrders = FuelRequest::where('user_id', $user->id)->count();
        $totalSpent = FuelRequest::where('user_id', $user->id)->where('status', FuelRequest::STATUS_DELIVERED)->sum('total_amount');
        $activeOrder = FuelRequest::where('user_id', $user->id)
            ->whereIn('status', FuelRequest::ACTIVE_STATUSES)
            ->with(['fuelProduct', 'agent.user', 'billing'])
            ->latest()->first();
        $recentOrders = FuelRequest::where('user_id', $user->id)
            ->with(['fuelProduct', 'billing'])
            ->latest()->take(5)->get();

        return view('user.dashboard', compact('totalOrders', 'totalSpent', 'activeOrder', 'recentOrders'));
    }

    public function orderForm() {
        $products = FuelProduct::where('is_available', true)->get();
        return view('user.order', compact('products'));
    }

    public function placeOrder(
        Request $request,
        BillingService $billingService,
        EmailOtpService $emailOtpService,
        UserWalletService $userWalletService,
    ) {
        $data = $request->validate([
            'fuel_product_id' => 'required|exists:fuel_products,id',
            'quantity_liters' => 'required|numeric|min:1|max:50',
            'delivery_address' => 'required|string',
            'location_mode' => 'required|in:live_gps,map_pin',
            'delivery_lat' => 'required|numeric|between:-90,90',
            'delivery_lng' => 'required|numeric|between:-180,180',
            'payment_method' => 'required|in:cod,online,wallet,upi,card',
            'notes' => 'nullable|string',
        ]);

        if (! $emailOtpService->hasFreshVerification($request, EmailOtpService::PURPOSE_ORDER, (string) Auth::user()?->email)) {
            return back()
                ->withInput()
                ->with('error', 'Please verify the email code before placing your order.');
        }

        $product = FuelProduct::findOrFail($data['fuel_product_id']);
        $distanceKm = $billingService->resolveOrderDistance(
            (float) $data['delivery_lat'],
            (float) $data['delivery_lng'],
        );

        if (! $billingService->isDistanceWithinServiceRange($distanceKm)) {
            return back()
                ->withInput()
                ->with('error', 'Delivery distance must be within 25 km.');
        }

        $paymentMethod = $billingService->normalizePaymentMethod($data['payment_method']);

        try {
            $order = DB::transaction(function () use ($billingService, $data, $distanceKm, $paymentMethod, $product, $userWalletService) {
                $order = FuelRequest::create([
                    'user_id' => Auth::id(),
                    'fuel_product_id' => $product->id,
                    'status' => FuelRequest::STATUS_PENDING,
                    'is_cancelled' => false,
                    'quantity_liters' => $data['quantity_liters'],
                    'fuel_price_per_liter' => $product->price_per_liter,
                    'delivery_charge' => 0,
                    'slab_charge' => 0,
                    'handling_fee' => 0,
                    'night_fee' => 0,
                    'surge_fee' => 0,
                    'priority_fee' => 0,
                    'long_distance_fee' => 0,
                    'pump_earning' => 0,
                    'platform_earning' => 0,
                    'total_amount' => 0,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $billingService->defaultPaymentStatus($paymentMethod),
                    'delivery_address' => $data['delivery_address'],
                    'location_mode' => $data['location_mode'],
                    'delivery_lat' => $data['delivery_lat'],
                    'delivery_lng' => $data['delivery_lng'],
                    'estimated_delivery_minutes' => DeliveryMetrics::estimateMinutes($distanceKm, 'accepted') ?? 30,
                    'distance_km' => $distanceKm,
                    'booked_distance_km' => $distanceKm,
                    'agent_last_movement_at' => null,
                    'notes' => $data['notes'] ?? null,
                ]);

                $billing = $billingService->createEstimatedBilling($order, $distanceKm);

                if ($paymentMethod === 'wallet') {
                    $userWalletService->debit($order->user()->firstOrFail(), (float) $billing->total_amount);
                }

                return $order->fresh();
            });
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        $emailOtpService->clearState($request, EmailOtpService::PURPOSE_ORDER);

        return redirect()->route('user.track', $order)->with('success', 'Order placed successfully! We\'re finding an agent for you.');
    }

    public function track($id) {
        $order = FuelRequest::where('user_id', Auth::id())
            ->with(['fuelProduct', 'agent.user', 'billing'])
            ->findOrFail($id);

        if ($order->agent && in_array($order->status, [
            FuelRequest::STATUS_ACCEPTED,
            FuelRequest::STATUS_FUEL_PREPARING,
            FuelRequest::STATUS_ON_THE_WAY,
            FuelRequest::STATUS_ARRIVED,
            FuelRequest::STATUS_OTP_VERIFICATION,
        ], true)) {
            $order = DeliveryMetrics::syncOrder($order, $order->agent)->load(['fuelProduct', 'agent.user', 'billing']);
        }

        return view('user.track', compact('order'));
    }

    public function trackData($id)
    {
        $order = FuelRequest::query()
            ->where('user_id', Auth::id())
            ->with(['agent.user'])
            ->findOrFail($id);

        if ($order->agent && in_array($order->status, [
            FuelRequest::STATUS_ACCEPTED,
            FuelRequest::STATUS_FUEL_PREPARING,
            FuelRequest::STATUS_ON_THE_WAY,
            FuelRequest::STATUS_ARRIVED,
            FuelRequest::STATUS_OTP_VERIFICATION,
        ], true)) {
            $order = DeliveryMetrics::syncOrder($order, $order->agent)->load('agent.user');
        }

        $trackingEnabled = $order->agent !== null
            && $order->status === FuelRequest::STATUS_ON_THE_WAY
            && $order->delivery_lat !== null
            && $order->delivery_lng !== null;

        return response()->json([
            'status' => $order->status,
            'tracking_enabled' => $trackingEnabled,
            'agent_id' => $order->agent_id,
            'agent_name' => $order->agent?->user?->name,
            'agent_latitude' => $order->agent?->trackingLatitude(),
            'agent_longitude' => $order->agent?->trackingLongitude(),
            'user_latitude' => $order->delivery_lat,
            'user_longitude' => $order->delivery_lng,
            'distance_km' => $order->distance_km,
            'estimated_delivery_minutes' => $order->estimated_delivery_minutes,
            'last_location_update' => $order->agent?->last_location_update?->toIso8601String(),
        ]);
    }

    public function updateLocation(Request $request, $id) {
        $order = FuelRequest::where('user_id', Auth::id())
            ->whereIn('status', [
                FuelRequest::STATUS_PENDING,
                FuelRequest::STATUS_ACCEPTED,
                FuelRequest::STATUS_FUEL_PREPARING,
                FuelRequest::STATUS_ON_THE_WAY,
                FuelRequest::STATUS_ARRIVED,
                FuelRequest::STATUS_OTP_VERIFICATION,
            ])
            ->with('agent')
            ->findOrFail($id);

        if (! $order->usesLiveCustomerLocation()) {
            if ($order->agent) {
                $order = DeliveryMetrics::syncOrder($order, $order->agent);
            }

            return response()->json([
                'ok' => true,
                'location_mode' => $order->location_mode,
                'message' => 'This order uses a fixed map pin and will not auto-sync from device GPS.',
                'delivery_lat' => $order->delivery_lat,
                'delivery_lng' => $order->delivery_lng,
                'distance_km' => $order->distance_km,
                'estimated_delivery_minutes' => $order->estimated_delivery_minutes,
            ]);
        }

        $data = $request->validate([
            'delivery_lat' => 'required|numeric|between:-90,90',
            'delivery_lng' => 'required|numeric|between:-180,180',
        ]);

        $order->update([
            'delivery_lat' => $data['delivery_lat'],
            'delivery_lng' => $data['delivery_lng'],
        ]);

        if ($order->agent) {
            $order = DeliveryMetrics::syncOrder($order, $order->agent);
        }

        return response()->json([
            'ok' => true,
            'delivery_lat' => $order->delivery_lat,
            'delivery_lng' => $order->delivery_lng,
            'distance_km' => $order->distance_km,
            'estimated_delivery_minutes' => $order->estimated_delivery_minutes,
        ]);
    }

    public function history() {
        $orders = FuelRequest::where('user_id', Auth::id())
            ->with(['fuelProduct', 'billing'])
            ->latest()->paginate(15);
        return view('user.history', compact('orders'));
    }

    public function supportIndex() {
        $tickets = SupportTicket::where('user_id', Auth::id())->latest()->get();
        return view('user.support', compact('tickets'));
    }

    public function supportStore(Request $request) {
        $data = $request->validate([
            'subject' => 'required|string|max:200',
            'message' => 'required|string',
        ]);

        SupportTicket::create([
            'user_id' => Auth::id(),
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'open',
        ]);

        return back()->with('success', 'Support ticket submitted successfully.');
    }
}
