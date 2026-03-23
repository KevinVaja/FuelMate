<?php
namespace Database\Seeders;

use App\Models\User;
use App\Models\Agent;
use App\Models\FuelProduct;
use App\Models\FuelRequest;
use App\Models\ServiceArea;
use App\Models\DeliveryPricingSetting;
use App\Models\DeliverySlab;
use App\Services\AdminWalletService;
use App\Services\BillingService;
use App\Services\OrderCancellationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder {
    public function run(): void {
        // Admin
        $admin = User::create(['name'=>'Admin User','email'=>'admin@fuelmate.com','phone'=>'9000000001','password'=>Hash::make('admin123'),'role'=>'admin','status'=>'active']);

        // Agent user
        $agentUser = User::create(['name'=>'Ravi Kumar','email'=>'agent@fuelmate.com','phone'=>'9000000002','password'=>Hash::make('agent123'),'role'=>'agent','status'=>'active']);
        $agent = Agent::create([
            'user_id'=>$agentUser->id,
            'vehicle_type'=>'Motorcycle',
            'vehicle_license_plate'=>'MH01AB1234',
            'approval_status'=>'approved',
            'verification_status'=>'approved',
            'approved_at'=>now(),
            'is_available'=>true,
            'rating'=>4.8,
            'total_deliveries'=>0,
            'wallet_balance'=>0,
            'current_lat'=>19.076,
            'current_lng'=>72.877,
        ]);

        // Regular user
        $user = User::create([
            'name'=>'Priya Sharma',
            'email'=>'user@fuelmate.com',
            'phone'=>'9000000003',
            'password'=>Hash::make('user123'),
            'role'=>'user',
            'status'=>'active',
            'wallet_balance'=>1500,
        ]);

        // Extra users
        User::create(['name'=>'Amit Singh','email'=>'amit@example.com','phone'=>'9000000004','password'=>Hash::make('password'),'role'=>'user']);

        // Fuel products
        $petrol = FuelProduct::create(['name'=>'Regular Petrol','fuel_type'=>'petrol','price_per_liter'=>102.50,'is_available'=>true,'description'=>'Standard unleaded petrol for everyday use']);
        $diesel = FuelProduct::create(['name'=>'Regular Diesel','fuel_type'=>'diesel','price_per_liter'=>89.75,'is_available'=>true,'description'=>'Standard diesel fuel']);
        $premPetrol = FuelProduct::create(['name'=>'Premium Petrol','fuel_type'=>'premium_petrol','price_per_liter'=>112.00,'is_available'=>true,'description'=>'High-octane premium petrol']);
        $premDiesel = FuelProduct::create(['name'=>'Premium Diesel','fuel_type'=>'premium_diesel','price_per_liter'=>98.50,'is_available'=>true,'description'=>'Premium diesel for heavy vehicles']);

        // Delivery slabs
        DeliverySlab::create(['min_km'=>0,'max_km'=>3,'charge'=>40]);
        DeliverySlab::create(['min_km'=>3.01,'max_km'=>6,'charge'=>70]);
        DeliverySlab::create(['min_km'=>6.01,'max_km'=>10,'charge'=>100]);
        DeliverySlab::create(['min_km'=>10.01,'max_km'=>25,'charge'=>150]);

        DeliveryPricingSetting::firstOrCreate([], [
            'surge_enabled' => false,
            'night_delivery_enabled' => true,
            'night_delivery_fee' => config('delivery_pricing.night_delivery_fee', 20),
            'night_starts_at' => config('delivery_pricing.night_starts_at', '22:00') . ':00',
            'night_ends_at' => config('delivery_pricing.night_ends_at', '06:00') . ':00',
        ]);

        // Service areas
        ServiceArea::create(['name'=>'Andheri West','city'=>'Mumbai','zone'=>'West','is_active'=>true]);
        ServiceArea::create(['name'=>'Bandra','city'=>'Mumbai','zone'=>'West','is_active'=>true]);
        ServiceArea::create(['name'=>'Powai','city'=>'Mumbai','zone'=>'East','is_active'=>true]);
        ServiceArea::create(['name'=>'Borivali','city'=>'Mumbai','zone'=>'North','is_active'=>true]);

        // Sample orders and billing states
        $completedOrder = FuelRequest::create([
            'user_id'=>$user->id,
            'agent_id'=>$agent->id,
            'fuel_product_id'=>$petrol->id,
            'status'=>'delivered',
            'quantity_liters'=>5,
            'fuel_price_per_liter'=>102.50,
            'delivery_charge'=>0,
            'total_amount'=>0,
            'payment_method'=>'online',
            'payment_status'=>'paid',
            'delivery_address'=>'Andheri West, Mumbai',
            'delivery_lat'=>19.1136,
            'delivery_lng'=>72.8697,
            'estimated_delivery_minutes'=>0,
            'distance_km'=>2.5,
            'booked_distance_km'=>2.5,
            'created_at'=>now()->subDays(5),
            'updated_at'=>now()->subDays(5),
        ]);

        $pendingOrder = FuelRequest::create([
            'user_id'=>$user->id,
            'agent_id'=>null,
            'fuel_product_id'=>$diesel->id,
            'status'=>'pending',
            'quantity_liters'=>10,
            'fuel_price_per_liter'=>89.75,
            'delivery_charge'=>0,
            'total_amount'=>0,
            'payment_method'=>'cod',
            'payment_status'=>'pending',
            'delivery_address'=>'Bandra, Mumbai',
            'delivery_lat'=>19.0596,
            'delivery_lng'=>72.8295,
            'estimated_delivery_minutes'=>30,
            'distance_km'=>5.0,
            'booked_distance_km'=>5.0,
        ]);

        $acceptedOrder = FuelRequest::create([
            'user_id'=>$user->id,
            'agent_id'=>$agent->id,
            'fuel_product_id'=>$premPetrol->id,
            'status'=>'accepted',
            'quantity_liters'=>7,
            'fuel_price_per_liter'=>112.00,
            'delivery_charge'=>0,
            'total_amount'=>0,
            'payment_method'=>'wallet',
            'payment_status'=>'paid',
            'delivery_address'=>'Powai, Mumbai',
            'delivery_lat'=>19.1176,
            'delivery_lng'=>72.9060,
            'estimated_delivery_minutes'=>18,
            'distance_km'=>8.2,
            'booked_distance_km'=>8.2,
            'created_at'=>now()->subDay(),
            'updated_at'=>now()->subDay(),
        ]);

        /** @var BillingService $billingService */
        $billingService = app(BillingService::class);
        /** @var AdminWalletService $adminWalletService */
        $adminWalletService = app(AdminWalletService::class);
        /** @var OrderCancellationService $orderCancellationService */
        $orderCancellationService = app(OrderCancellationService::class);

        $billingService->createEstimatedBilling($pendingOrder, (float) $pendingOrder->booked_distance_km);
        $billingService->finalizeBilling($acceptedOrder);
        $billingService->finalizeBilling($completedOrder);
        $billingService->markPaymentCaptured(
            $completedOrder->fresh('billing'),
            'SEED-ONLINE-001',
            'seed_gateway',
            ['source' => 'database_seeder']
        );
        $billingService->settleDeliveredOrder($completedOrder->fresh(['billing', 'agent']));

        $adminWalletService->resolvePrimaryAdminAccount()->update([
            'wallet_balance' => 8000,
        ]);

        $cancelledPendingOrder = FuelRequest::create([
            'user_id'=>$user->id,
            'agent_id'=>null,
            'fuel_product_id'=>$diesel->id,
            'status'=>'pending',
            'quantity_liters'=>8,
            'fuel_price_per_liter'=>89.75,
            'delivery_charge'=>0,
            'total_amount'=>0,
            'payment_method'=>'cod',
            'payment_status'=>'pending',
            'delivery_address'=>'Borivali, Mumbai',
            'location_mode'=>'map_pin',
            'delivery_lat'=>19.2290,
            'delivery_lng'=>72.8570,
            'estimated_delivery_minutes'=>28,
            'distance_km'=>4.8,
            'booked_distance_km'=>4.8,
            'created_at'=>now()->subHours(8),
            'updated_at'=>now()->subHours(8),
        ]);
        $billingService->createEstimatedBilling($cancelledPendingOrder, (float) $cancelledPendingOrder->booked_distance_km);
        $orderCancellationService->cancelOrder(
            $cancelledPendingOrder,
            FuelRequest::CANCELLED_BY_CUSTOMER,
            'Changed plans after placing the order.'
        );

        $refundProcessingOrder = FuelRequest::create([
            'user_id'=>$user->id,
            'agent_id'=>$agent->id,
            'fuel_product_id'=>$premDiesel->id,
            'status'=>'accepted',
            'quantity_liters'=>6,
            'fuel_price_per_liter'=>98.50,
            'delivery_charge'=>0,
            'total_amount'=>0,
            'payment_method'=>'wallet',
            'payment_status'=>'paid',
            'delivery_address'=>'Bandra Kurla Complex, Mumbai',
            'location_mode'=>'live_gps',
            'delivery_lat'=>19.0679,
            'delivery_lng'=>72.8690,
            'estimated_delivery_minutes'=>16,
            'distance_km'=>5.5,
            'booked_distance_km'=>5.5,
            'agent_last_movement_at'=>now()->subMinutes(2),
            'created_at'=>now()->subHours(5),
            'updated_at'=>now()->subHours(5),
        ]);
        $billingService->finalizeBilling($refundProcessingOrder);
        $orderCancellationService->cancelOrder(
            $refundProcessingOrder,
            FuelRequest::CANCELLED_BY_CUSTOMER,
            'Customer cancelled before dispatch left the station.'
        );

        $approvedRefundOrder = FuelRequest::create([
            'user_id'=>$user->id,
            'agent_id'=>$agent->id,
            'fuel_product_id'=>$petrol->id,
            'status'=>'on_the_way',
            'quantity_liters'=>4,
            'fuel_price_per_liter'=>102.50,
            'delivery_charge'=>0,
            'total_amount'=>0,
            'payment_method'=>'online',
            'payment_status'=>'paid',
            'delivery_address'=>'Powai Lake Road, Mumbai',
            'location_mode'=>'map_pin',
            'delivery_lat'=>19.1197,
            'delivery_lng'=>72.9054,
            'estimated_delivery_minutes'=>9,
            'distance_km'=>7.4,
            'booked_distance_km'=>7.4,
            'agent_last_movement_at'=>now()->subMinutes(1),
            'created_at'=>now()->subHours(3),
            'updated_at'=>now()->subHours(3),
        ]);
        $billingService->finalizeBilling($approvedRefundOrder);
        $billingService->markPaymentCaptured(
            $approvedRefundOrder->fresh('billing'),
            'SEED-ONLINE-REFUND-001',
            'seed_gateway',
            ['source' => 'database_seeder']
        );
        $orderCancellationService->cancelOrder(
            $approvedRefundOrder->fresh(['billing', 'user', 'agent']),
            FuelRequest::CANCELLED_BY_ADMIN,
            'Admin force cancelled a dispatched order for refund testing.'
        );
        $orderCancellationService->approveRefund($approvedRefundOrder->fresh(['billing', 'user', 'agent']));

        $agent->update([
            'total_deliveries' => FuelRequest::query()
                ->where('agent_id', $agent->id)
                ->whereIn('status', ['delivered', 'completed'])
                ->count(),
        ]);
    }
}
