<?php
namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Billing;
use App\Models\FuelRequest;
use App\Services\BillingService;
use App\Support\DeliveryMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller {

    public function __construct(
        private readonly BillingService $billingService,
    ) {
    }

    private function agent(): Agent {
        return Auth::user()->agent;
    }

    public function dashboard() {
        $agent = $this->agent();
        $trendStart = Carbon::today()->subDays(6)->startOfDay();
        $todayEarnings = $this->completedBillingQuery($agent->id)
            ->whereDate('fuel_requests.updated_at', today())
            ->sum('billings.agent_earning');
        $weekEarnings = $this->completedBillingQuery($agent->id)
            ->whereBetween('fuel_requests.updated_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('billings.agent_earning');
        $activeOrder = $this->resolveAgentVisibleOrder($agent->id);
        $performanceByDay = $this->completedBillingQuery($agent->id)
            ->selectRaw('DATE(fuel_requests.updated_at) as chart_date, SUM(billings.agent_earning) as total_earnings, COUNT(*) as total_deliveries')
            ->where('fuel_requests.updated_at', '>=', $trendStart)
            ->groupBy('chart_date')
            ->get()
            ->keyBy('chart_date');
        $agentPerformance = collect(range(0, 6))
            ->map(function (int $offset) use ($performanceByDay) {
                $date = Carbon::today()->subDays(6 - $offset);
                $point = $performanceByDay->get($date->toDateString());

                return [
                    'short_label' => $date->format('D'),
                    'label' => $date->format('d M'),
                    'deliveries' => (int) ($point->total_deliveries ?? 0),
                    'earnings' => (float) ($point->total_earnings ?? 0),
                ];
            })
            ->all();
        $agentStatusCounts = FuelRequest::query()
            ->where('agent_id', $agent->id)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');
        $agentStatusBreakdown = collect([
            FuelRequest::STATUS_ACCEPTED,
            FuelRequest::STATUS_FUEL_PREPARING,
            FuelRequest::STATUS_ON_THE_WAY,
            FuelRequest::STATUS_ARRIVED,
            FuelRequest::STATUS_OTP_VERIFICATION,
            FuelRequest::STATUS_DELIVERED,
            FuelRequest::STATUS_CANCELLED,
            FuelRequest::STATUS_REFUND_PROCESSING,
        ])
            ->map(function (string $status) use ($agentStatusCounts) {
                return [
                    'status' => $status,
                    'count' => (int) ($agentStatusCounts[$status] ?? 0),
                ];
            })
            ->filter(fn (array $item) => $item['count'] > 0)
            ->values()
            ->all();

        return view('agent.dashboard', compact(
            'agent',
            'todayEarnings',
            'weekEarnings',
            'activeOrder',
            'agentPerformance',
            'agentStatusBreakdown'
        ));
    }

    public function requests() {
        $agent = $this->agent();
        $requests = [];
        if ($agent->isApprovedForOperations() && $agent->is_available) {
            $requests = FuelRequest::where('status', 'pending')
                ->whereNull('agent_id')
                ->with(['user', 'fuelProduct', 'billing'])
                ->latest()->get()
                ->map(fn (FuelRequest $request) => $this->hydrateRequestMetrics($request, $agent));
        }
        return view('agent.requests', compact('requests'));
    }

    public function accept(Request $request, $id) {
        $agent = $this->agent();
        if (! $agent->isApprovedForOperations()) {
            return back()->with('error', 'Your petrol pump account is under verification.');
        }

        $this->syncAgentLocationFromRequest($agent, $request);

        $hasActive = FuelRequest::where('agent_id', $agent->id)
            ->whereIn('status', [
                FuelRequest::STATUS_ACCEPTED,
                FuelRequest::STATUS_FUEL_PREPARING,
                FuelRequest::STATUS_ON_THE_WAY,
                FuelRequest::STATUS_ARRIVED,
                FuelRequest::STATUS_OTP_VERIFICATION,
            ])
            ->exists();
        if ($hasActive) {
            return back()->with('error', 'You already have an active delivery. Complete it first.');
        }

        DB::transaction(function () use ($agent, $id) {
            $fuelRequest = FuelRequest::query()
                ->whereKey($id)
                ->where('status', 'pending')
                ->whereNull('agent_id')
                ->lockForUpdate()
                ->firstOrFail();

            $fuelRequest->update([
                'agent_id' => $agent->id,
                'status' => FuelRequest::STATUS_ACCEPTED,
                'agent_last_movement_at' => now(),
            ]);

            $fuelRequest = DeliveryMetrics::syncOrder($fuelRequest->fresh()->load(['agent', 'billing']), $agent);
            $fuelRequest->forceFill([
                'booked_distance_km' => $fuelRequest->distance_km,
            ])->save();

            $this->billingService->finalizeBilling($fuelRequest->fresh(['billing']));
        });

        return redirect()->route('agent.active')->with('success', 'Order accepted! Head to the customer.');
    }

    public function active() {
        $agent = $this->agent();
        $order = $this->resolveAgentVisibleOrder($agent->id);

        if ($order && in_array($order->status, [
            FuelRequest::STATUS_ACCEPTED,
            FuelRequest::STATUS_FUEL_PREPARING,
            FuelRequest::STATUS_ON_THE_WAY,
            FuelRequest::STATUS_ARRIVED,
            FuelRequest::STATUS_OTP_VERIFICATION,
        ], true)) {
            $order = DeliveryMetrics::syncOrder($order, $agent)->load(['user', 'fuelProduct', 'billing']);
        }

        return view('agent.active', compact('order'));
    }

    public function updateStatus(Request $request, $id) {
        $agent = $this->agent();
        $this->syncAgentLocationFromRequest($agent, $request);
        $order = FuelRequest::where('agent_id', $agent->id)->findOrFail($id);

        if ($request->filled('delivery_otp')
            && $order->hasDeliveryOtp()
            && in_array($order->status, [
                FuelRequest::STATUS_ON_THE_WAY,
                FuelRequest::STATUS_ARRIVED,
                FuelRequest::STATUS_OTP_VERIFICATION,
            ], true)) {
            return $this->verifyDeliveryOtp($request, $order);
        }

        if ($order->status === FuelRequest::STATUS_ACCEPTED) {
            $order->update([
                'status' => FuelRequest::STATUS_FUEL_PREPARING,
                'delivery_otp' => null,
                'delivery_otp_generated_at' => null,
                'delivery_otp_verified_at' => null,
            ]);

            DeliveryMetrics::syncOrder($order, $agent);

            return back()->with('success', 'Status updated to Fuel Preparing.');
        }

        if ($order->status === FuelRequest::STATUS_FUEL_PREPARING) {
            $order->update([
                'status' => FuelRequest::STATUS_ON_THE_WAY,
                'delivery_otp' => null,
                'delivery_otp_generated_at' => null,
                'delivery_otp_verified_at' => null,
            ]);

            DeliveryMetrics::syncOrder($order, $agent);

            return back()->with('success', 'Status updated to On The Way.');
        }

        if ($order->status === FuelRequest::STATUS_ON_THE_WAY) {
            $order->update([
                'status' => FuelRequest::STATUS_ARRIVED,
                'estimated_delivery_minutes' => 0,
            ]);

            return back()->with('success', 'Status updated to Arrived. Generate the OTP when the handoff begins.');
        }

        if ($order->status === FuelRequest::STATUS_ARRIVED) {
            $order->update([
                'status' => FuelRequest::STATUS_OTP_VERIFICATION,
                'delivery_otp' => (string) random_int(100000, 999999),
                'delivery_otp_generated_at' => now(),
                'delivery_otp_verified_at' => null,
            ]);

            return back()->with('success', 'Delivery OTP generated. Ask the customer to share this demo OTP from the tracking page.');
        }

        if ($order->status === FuelRequest::STATUS_OTP_VERIFICATION) {
            return $this->verifyDeliveryOtp($request, $order);
        }

        if ($order->status === FuelRequest::STATUS_DELIVERED) {
            DB::transaction(function () use ($order) {
                $lockedOrder = FuelRequest::query()
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                $lockedOrder->update([
                    'status' => FuelRequest::STATUS_COMPLETED_LEGACY,
                    'estimated_delivery_minutes' => 0,
                ]);

                if ($lockedOrder->agent_id !== null) {
                    $agent = Agent::query()
                        ->lockForUpdate()
                        ->findOrFail($lockedOrder->agent_id);

                    $completedDeliveries = FuelRequest::query()
                        ->where('agent_id', $lockedOrder->agent_id)
                        ->whereIn('status', [
                            FuelRequest::STATUS_DELIVERED,
                            FuelRequest::STATUS_COMPLETED_LEGACY,
                        ])
                        ->count();

                    if ((int) $agent->total_deliveries < $completedDeliveries) {
                        $agent->update([
                            'total_deliveries' => $completedDeliveries,
                        ]);
                    }
                }
            });

            return back()->with('success', 'Order archived as completed.');
        }

        return back()->with('error', 'Cannot update status.');
    }

    public function toggleAvailability() {
        $agent = $this->agent();
        $agent->update(['is_available' => !$agent->is_available]);
        return back()->with('success', 'Availability updated.');
    }

    public function updateLocation(Request $request) {
        $agent = $this->agent();
        $data = $request->validate([
            'current_lat' => 'required|numeric|between:-90,90',
            'current_lng' => 'required|numeric|between:-180,180',
        ]);

        $previousLat = $agent->current_lat;
        $previousLng = $agent->current_lng;

        $agent->update([
            'current_lat' => $data['current_lat'],
            'current_lng' => $data['current_lng'],
        ]);

        $order = FuelRequest::where('agent_id', $agent->id)
            ->whereIn('status', [
                FuelRequest::STATUS_ACCEPTED,
                FuelRequest::STATUS_FUEL_PREPARING,
                FuelRequest::STATUS_ON_THE_WAY,
                FuelRequest::STATUS_ARRIVED,
                FuelRequest::STATUS_OTP_VERIFICATION,
            ])
            ->latest()
            ->first();

        if ($order) {
            $order = DeliveryMetrics::syncOrder($order, $agent);

            $movedDistance = DeliveryMetrics::distanceKm(
                $previousLat,
                $previousLng,
                $agent->current_lat,
                $agent->current_lng,
            );

            if ($movedDistance !== null && $movedDistance >= 0.05) {
                $order->forceFill([
                    'agent_last_movement_at' => now(),
                ])->save();
            }
        }

        return response()->json([
            'ok' => true,
            'current_lat' => $agent->current_lat,
            'current_lng' => $agent->current_lng,
            'distance_km' => $order?->distance_km,
            'estimated_delivery_minutes' => $order?->estimated_delivery_minutes,
            'navigation_url' => $order?->googleMapsDirectionsUrl($agent->current_lat, $agent->current_lng),
        ]);
    }

    public function history() {
        $agent = $this->agent();
        $orders = FuelRequest::where('agent_id', $agent->id)
            ->whereIn('status', [
                FuelRequest::STATUS_DELIVERED,
                FuelRequest::STATUS_CANCELLED,
                FuelRequest::STATUS_REFUND_PROCESSING,
                FuelRequest::STATUS_COMPLETED_LEGACY,
            ])
            ->with(['user', 'fuelProduct', 'billing'])
            ->latest()->paginate(15);
        return view('agent.history', compact('orders'));
    }

    public function earnings() {
        $agent = $this->agent();
        $data = [
            'today_earnings' => $this->completedBillingQuery($agent->id)
                ->whereDate('fuel_requests.updated_at', today())
                ->sum('billings.agent_earning'),
            'today_deliveries' => FuelRequest::where('agent_id', $agent->id)->whereIn('status', [FuelRequest::STATUS_DELIVERED, FuelRequest::STATUS_COMPLETED_LEGACY])->whereDate('updated_at', today())->count(),
            'week_earnings' => $this->completedBillingQuery($agent->id)
                ->whereBetween('fuel_requests.updated_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->sum('billings.agent_earning'),
            'week_deliveries' => FuelRequest::where('agent_id', $agent->id)->whereIn('status', [FuelRequest::STATUS_DELIVERED, FuelRequest::STATUS_COMPLETED_LEGACY])->whereBetween('updated_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'total_earnings' => $this->completedBillingQuery($agent->id)
                ->sum('billings.agent_earning'),
            'total_deliveries' => $agent->total_deliveries,
        ];
        $recentOrders = FuelRequest::where('agent_id', $agent->id)
            ->whereIn('status', [FuelRequest::STATUS_DELIVERED, FuelRequest::STATUS_COMPLETED_LEGACY])
            ->with(['fuelProduct', 'billing'])
            ->latest()->take(10)->get();
        return view('agent.earnings', compact('data', 'recentOrders'));
    }

    private function syncAgentLocationFromRequest(Agent $agent, Request $request): void {
        $data = $request->validate([
            'current_lat' => 'nullable|numeric|between:-90,90',
            'current_lng' => 'nullable|numeric|between:-180,180',
        ]);

        if (($data['current_lat'] ?? null) === null || ($data['current_lng'] ?? null) === null) {
            return;
        }

        $previousLat = $agent->current_lat;
        $previousLng = $agent->current_lng;

        $agent->update([
            'current_lat' => $data['current_lat'],
            'current_lng' => $data['current_lng'],
        ]);

        $movedDistance = DeliveryMetrics::distanceKm(
            $previousLat,
            $previousLng,
            $agent->current_lat,
            $agent->current_lng,
        );

        if ($movedDistance === null || $movedDistance < 0.05) {
            return;
        }

        FuelRequest::query()
            ->where('agent_id', $agent->id)
            ->whereIn('status', [
                FuelRequest::STATUS_ACCEPTED,
                FuelRequest::STATUS_FUEL_PREPARING,
                FuelRequest::STATUS_ON_THE_WAY,
                FuelRequest::STATUS_ARRIVED,
                FuelRequest::STATUS_OTP_VERIFICATION,
            ])
            ->update([
                'agent_last_movement_at' => now(),
            ]);
    }

    private function hydrateRequestMetrics(FuelRequest $request, Agent $agent): FuelRequest {
        $distanceKm = DeliveryMetrics::distanceKm(
            $agent->current_lat,
            $agent->current_lng,
            $request->delivery_lat,
            $request->delivery_lng,
        );

        if ($distanceKm !== null) {
            $request->distance_km = $distanceKm;
            $request->estimated_delivery_minutes = DeliveryMetrics::estimateMinutes($distanceKm, 'accepted');
        }

        return $request;
    }

    private function completedBillingQuery(int $agentId)
    {
        return Billing::query()
            ->join('fuel_requests', 'fuel_requests.id', '=', 'billings.order_id')
            ->where('fuel_requests.agent_id', $agentId)
            ->whereIn('fuel_requests.status', [
                FuelRequest::STATUS_DELIVERED,
                FuelRequest::STATUS_COMPLETED_LEGACY,
            ]);
    }

    private function resolveAgentVisibleOrder(int $agentId): ?FuelRequest
    {
        $baseQuery = FuelRequest::query()
            ->where('agent_id', $agentId)
            ->with(['user', 'fuelProduct', 'billing'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $activeOrder = (clone $baseQuery)
            ->whereIn('status', [
                FuelRequest::STATUS_ACCEPTED,
                FuelRequest::STATUS_FUEL_PREPARING,
                FuelRequest::STATUS_ON_THE_WAY,
                FuelRequest::STATUS_ARRIVED,
                FuelRequest::STATUS_OTP_VERIFICATION,
            ])
            ->first();

        if ($activeOrder) {
            return $activeOrder;
        }

        return (clone $baseQuery)
            ->whereIn('status', [
                FuelRequest::STATUS_DELIVERED,
                FuelRequest::STATUS_CANCELLED,
                FuelRequest::STATUS_REFUND_PROCESSING,
            ])
            ->where('updated_at', '>=', now()->subDay())
            ->first();
    }

    private function verifyDeliveryOtp(Request $request, FuelRequest $order)
    {
        $data = $request->validate([
            'delivery_otp' => 'required|digits:6',
        ]);

        if ($data['delivery_otp'] !== $order->delivery_otp) {
            return back()->with('error', 'Invalid delivery OTP. Please confirm the OTP from the customer and try again.');
        }

        DB::transaction(function () use ($order) {
            $lockedOrder = FuelRequest::query()
                ->with(['billing', 'agent'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $lockedOrder->update([
                'status' => FuelRequest::STATUS_DELIVERED,
                'estimated_delivery_minutes' => 0,
                'delivery_otp_verified_at' => now(),
            ]);

            $this->billingService->settleDeliveredOrder($lockedOrder);

            if ($lockedOrder->agent_id !== null) {
                Agent::query()
                    ->lockForUpdate()
                    ->findOrFail($lockedOrder->agent_id)
                    ->increment('total_deliveries');
            }
        });

        return back()->with('success', 'Delivery OTP verified. Order marked as delivered.');
    }
}
