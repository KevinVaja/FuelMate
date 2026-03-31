<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Agent;
use App\Models\Billing;
use App\Models\FuelProduct;
use App\Models\FuelRequest;
use App\Models\SupportTicket;
use App\Models\ServiceArea;
use App\Models\DeliverySlab;
use App\Services\AgentCoverageMonitorService;
use App\Services\DeliveryPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller {
    public function __construct(
        private readonly AgentCoverageMonitorService $agentCoverageMonitorService,
        private readonly DeliveryPricingService $deliveryPricingService,
    ) {
    }

    public function dashboard() {
        $trendStart = Carbon::today()->subDays(6)->startOfDay();
        $data = [
            'total_orders'    => FuelRequest::count(),
            'total_revenue'   => FuelRequest::whereIn('status', [FuelRequest::STATUS_DELIVERED, FuelRequest::STATUS_COMPLETED_LEGACY])->sum('total_amount'),
            'total_users'     => User::where('role', 'user')->count(),
            'active_agents'   => Agent::approvedForOperations()->count(),
            'pending_orders'  => FuelRequest::where('status', FuelRequest::STATUS_PENDING)->count(),
            'completed_orders'=> FuelRequest::whereIn('status', [FuelRequest::STATUS_DELIVERED, FuelRequest::STATUS_COMPLETED_LEGACY])->count(),
            'pending_agents'  => Agent::pendingVerification()->count(),
            'open_tickets'    => SupportTicket::where('status', 'open')->count(),
            'cancelled_orders' => FuelRequest::where('is_cancelled', true)->count(),
            'cancellation_charges_collected' => FuelRequest::where('is_cancelled', true)->sum('cancellation_charge'),
            'refund_pending' => Billing::query()->where('refund_status', Billing::REFUND_PENDING)->sum('refundable_amount'),
        ];
        $recentOrders = FuelRequest::with(['user','fuelProduct','billing'])->latest()->take(10)->get();
        $statusCounts = FuelRequest::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');
        $ordersByDay = FuelRequest::query()
            ->selectRaw('DATE(created_at) as chart_date, COUNT(*) as total_orders')
            ->where('created_at', '>=', $trendStart)
            ->groupBy('chart_date')
            ->pluck('total_orders', 'chart_date');
        $revenueByDay = FuelRequest::query()
            ->selectRaw('DATE(updated_at) as chart_date, SUM(total_amount) as total_revenue')
            ->whereIn('status', [FuelRequest::STATUS_DELIVERED, FuelRequest::STATUS_COMPLETED_LEGACY])
            ->where('updated_at', '>=', $trendStart)
            ->groupBy('chart_date')
            ->pluck('total_revenue', 'chart_date');
        $adminTrend = collect(range(0, 6))
            ->map(function (int $offset) use ($ordersByDay, $revenueByDay) {
                $date = Carbon::today()->subDays(6 - $offset);
                $key = $date->toDateString();

                return [
                    'short_label' => $date->format('D'),
                    'label' => $date->format('d M'),
                    'orders' => (int) ($ordersByDay[$key] ?? 0),
                    'revenue' => (float) ($revenueByDay[$key] ?? 0),
                ];
            })
            ->all();
        $orderStatusChart = collect([
            FuelRequest::STATUS_PENDING,
            FuelRequest::STATUS_ACCEPTED,
            FuelRequest::STATUS_FUEL_PREPARING,
            FuelRequest::STATUS_ON_THE_WAY,
            FuelRequest::STATUS_ARRIVED,
            FuelRequest::STATUS_OTP_VERIFICATION,
            FuelRequest::STATUS_DELIVERED,
            FuelRequest::STATUS_REFUND_PROCESSING,
            FuelRequest::STATUS_CANCELLED,
        ])
            ->map(function (string $status) use ($statusCounts, $data) {
                $count = (int) ($statusCounts[$status] ?? 0);

                return [
                    'status' => $status,
                    'count' => $count,
                    'percentage' => $data['total_orders'] > 0
                        ? (int) round(($count / $data['total_orders']) * 100)
                        : 0,
                ];
            })
            ->filter(fn (array $item) => $item['count'] > 0)
            ->values()
            ->all();

        return view('admin.dashboard', compact('data', 'recentOrders', 'adminTrend', 'orderStatusChart'));
    }

    // === ORDERS ===
    public function orders(Request $request) {
        $query = FuelRequest::with(['user', 'agent.user', 'fuelProduct', 'billing'])->latest();
        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('user', fn($q) => $q->where('name','like',"%$s%")->orWhere('email','like',"%$s%"))
                  ->orWhere('delivery_address','like',"%$s%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $orders = $query->paginate(20)->withQueryString();
        $summary = [
            'total_cancelled_orders' => FuelRequest::query()->where('is_cancelled', true)->count(),
            'total_cancellation_charges_collected' => FuelRequest::query()->where('is_cancelled', true)->sum('cancellation_charge'),
            'total_refund_pending' => Billing::query()->where('refund_status', Billing::REFUND_PENDING)->sum('refundable_amount'),
        ];

        return view('admin.orders', compact('orders', 'summary'));
    }

    // === USERS ===
    public function users(Request $request) {
        $query = User::where('role', 'user')->withCount('fuelRequests');
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name','like',"%$s%")->orWhere('email','like',"%$s%"));
        }
        $users = $query->latest()->paginate(20)->withQueryString();
        return view('admin.users', compact('users'));
    }

    public function toggleUser($id) {
        $user = User::findOrFail($id);
        $isBlocking = $user->status === 'active';

        $user->update([
            'status' => $isBlocking ? 'blocked' : 'active',
        ]);

        return back()->with('success', $isBlocking ? 'User suspended successfully.' : 'User activated successfully.');
    }

    // === AGENTS ===
    public function agents() {
        $agents = Agent::with('user')->latest()->paginate(20);
        return view('admin.agents', compact('agents'));
    }

    public function agentCoverage(Request $request)
    {
        $monitor = $this->agentCoverageMonitorService->build(
            $request->query('status'),
            $this->pumpFilterFromRequest($request),
        );

        return view('admin.agent_coverage', [
            'filters' => $monitor['filters'],
            'filterOptions' => $monitor['filter_options'],
            'agentDirectory' => $monitor['agent_directory'],
            'summary' => $monitor['summary'],
            'mapPayload' => $monitor['payload'],
        ]);
    }

    public function agentCoverageData(Request $request)
    {
        $monitor = $this->agentCoverageMonitorService->build(
            $request->query('status'),
            $this->pumpFilterFromRequest($request),
        );

        return response()->json($monitor['payload']);
    }

    public function approveAgent($id) {
        Agent::findOrFail($id)->markVerificationApproved();
        return back()->with('success', 'Agent approved.');
    }

    public function rejectAgent($id) {
        Agent::findOrFail($id)->markVerificationRejected('Rejected by admin.');
        return back()->with('success', 'Agent rejected/suspended.');
    }

    public function updateAgentPumpLocation(Request $request, $id)
    {
        $data = $request->validate([
            'pump_latitude' => 'required|numeric|between:-90,90',
            'pump_longitude' => 'required|numeric|between:-180,180',
        ]);

        Agent::query()->findOrFail($id)->update([
            'pump_latitude' => $data['pump_latitude'],
            'pump_longitude' => $data['pump_longitude'],
        ]);

        return back()->with('success', 'Pump location updated. Coverage circles and active markers now use the corrected location.');
    }

    // === PRODUCTS ===
    public function products() {
        $products = FuelProduct::all();
        return view('admin.products', compact('products'));
    }

    public function storeProduct(Request $request) {
        $data = $request->validate([
            'name' => 'required|string',
            'fuel_type' => 'required|in:petrol,diesel,premium_petrol,premium_diesel',
            'price_per_liter' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);
        FuelProduct::create(array_merge($data, ['is_available' => true]));
        return back()->with('success', 'Product added.');
    }

    public function updateProduct(Request $request, $id) {
        $data = $request->validate([
            'name' => 'required|string',
            'price_per_liter' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);
        FuelProduct::findOrFail($id)->update($data);
        return back()->with('success', 'Product updated.');
    }

    public function toggleProduct($id) {
        $p = FuelProduct::findOrFail($id);
        $p->update(['is_available' => !$p->is_available]);
        return back()->with('success', 'Product availability toggled.');
    }

    // === DELIVERY CHARGES ===
    public function deliveryCharges() {
        $charges = DeliverySlab::orderBy('min_km')->get();
        $nightDelivery = $this->deliveryPricingService->nightDeliverySettings();
        return view('admin.delivery_charges', compact('charges', 'nightDelivery'));
    }

    public function storeDeliveryCharge(Request $request) {
        $data = $request->validate([
            'min_km' => 'required|numeric|min:0',
            'max_km' => 'required|numeric|gt:min_km',
            'charge' => 'required|numeric|min:0',
        ]);
        DeliverySlab::create($data);
        return back()->with('success', 'Delivery slab added.');
    }

    public function updateDeliveryCharge(Request $request, $id) {
        $data = $request->validate([
            'min_km' => 'required|numeric|min:0',
            'max_km' => 'required|numeric|gt:min_km',
            'charge' => 'required|numeric|min:0',
        ]);
        DeliverySlab::findOrFail($id)->update($data);
        return back()->with('success', 'Delivery slab updated.');
    }

    public function deleteDeliveryCharge($id) {
        DeliverySlab::findOrFail($id)->delete();
        return back()->with('success', 'Delivery slab removed.');
    }

    public function updateNightDeliveryPricing(Request $request) {
        $data = $request->validate([
            'night_delivery_enabled' => 'required|boolean',
            'night_delivery_fee' => 'required|numeric|min:0',
            'night_starts_at' => 'required|date_format:H:i',
            'night_ends_at' => 'required|date_format:H:i',
        ]);

        $this->deliveryPricingService->updateNightDeliverySettings([
            'night_delivery_enabled' => (bool) $data['night_delivery_enabled'],
            'night_delivery_fee' => $data['night_delivery_fee'],
            'night_starts_at' => $data['night_starts_at'] . ':00',
            'night_ends_at' => $data['night_ends_at'] . ':00',
        ]);

        return back()->with('success', 'Night delivery pricing updated successfully.');
    }

    // === SERVICE AREAS ===
    public function serviceAreas() {
        $areas = ServiceArea::orderBy('city')->orderBy('name')->get();
        return view('admin.service_areas', compact('areas'));
    }

    public function storeServiceArea(Request $request) {
        $data = $request->validate(['name'=>'required|string','city'=>'required|string','zone'=>'nullable|string']);
        ServiceArea::create(array_merge($data, ['is_active' => true]));
        return back()->with('success', 'Service area added.');
    }

    public function toggleServiceArea($id) {
        $a = ServiceArea::findOrFail($id);
        $a->update(['is_active' => !$a->is_active]);
        return back()->with('success', 'Service area toggled.');
    }

    // === SUPPORT ===
    public function support(Request $request) {
        $query = SupportTicket::with('user')->latest();
        if ($request->filled('status')) $query->where('status', $request->status);
        $tickets = $query->paginate(20)->withQueryString();
        return view('admin.support', compact('tickets'));
    }

    public function respondTicket(Request $request, $id) {
        $data = $request->validate(['admin_response'=>'nullable|string','status'=>'required|in:open,in_progress,resolved,closed']);
        SupportTicket::findOrFail($id)->update($data);
        return back()->with('success', 'Ticket updated.');
    }

    private function pumpFilterFromRequest(Request $request): ?int
    {
        $pumpId = trim((string) $request->query('pump_id', ''));

        return ctype_digit($pumpId) ? (int) $pumpId : null;
    }
}
