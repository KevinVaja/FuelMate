<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class FuelRequest extends Model {
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_FUEL_PREPARING = 'fuel_preparing';
    public const STATUS_ON_THE_WAY = 'on_the_way';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_OTP_VERIFICATION = 'otp_verification';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUND_PROCESSING = 'refund_processing';
    public const STATUS_COMPLETED_LEGACY = 'completed';

    public const CANCELLED_BY_CUSTOMER = 'customer';
    public const CANCELLED_BY_AGENT = 'agent';
    public const CANCELLED_BY_ADMIN = 'admin';
    public const CANCELLED_BY_SYSTEM = 'system';

    public const CANCELLATION_CHARGE_PAYMENT_NONE = 'none';
    public const CANCELLATION_CHARGE_PAYMENT_PAID = 'paid';

    public const CANCELLATION_CHARGE_METHOD_WALLET = 'wallet';
    public const CANCELLATION_CHARGE_METHOD_ONLINE = 'online';

    public const CUSTOMER_CANCELLABLE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_FUEL_PREPARING,
    ];

    public const AGENT_CANCELLABLE_STATUSES = [
        self::STATUS_ACCEPTED,
        self::STATUS_FUEL_PREPARING,
    ];

    public const FORCE_CANCELLABLE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_FUEL_PREPARING,
        self::STATUS_ON_THE_WAY,
        self::STATUS_ARRIVED,
        self::STATUS_OTP_VERIFICATION,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_FUEL_PREPARING,
        self::STATUS_ON_THE_WAY,
        self::STATUS_ARRIVED,
        self::STATUS_OTP_VERIFICATION,
        self::STATUS_REFUND_PROCESSING,
    ];

    public const TRACKING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_FUEL_PREPARING,
        self::STATUS_ON_THE_WAY,
        self::STATUS_ARRIVED,
        self::STATUS_OTP_VERIFICATION,
        self::STATUS_DELIVERED,
        self::STATUS_REFUND_PROCESSING,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'user_id',
        'agent_id',
        'fuel_product_id',
        'status',
        'is_cancelled',
        'cancelled_by',
        'quantity_liters',
        'fuel_price_per_liter',
        'delivery_charge',
        'slab_charge',
        'handling_fee',
        'night_fee',
        'surge_fee',
        'priority_fee',
        'long_distance_fee',
        'pump_earning',
        'platform_earning',
        'total_amount',
        'payment_method',
        'payment_status',
        'delivery_address',
        'location_mode',
        'delivery_lat',
        'delivery_lng',
        'estimated_delivery_minutes',
        'distance_km',
        'booked_distance_km',
        'delivery_otp',
        'delivery_otp_generated_at',
        'delivery_otp_verified_at',
        'cancellation_reason',
        'cancelled_at',
        'cancellation_charge',
        'cancellation_charge_payment_status',
        'cancellation_charge_payment_method',
        'cancellation_charge_paid_at',
        'cancellation_charge_payment_reference',
        'agent_last_movement_at',
        'notes',
        'requestId',
        'userId',
        'fuelType',
        'quantity',
    ];
    protected $casts = [
        'quantity_liters' => 'float',
        'is_cancelled' => 'boolean',
        'fuel_price_per_liter' => 'float',
        'delivery_charge' => 'float',
        'slab_charge' => 'float',
        'handling_fee' => 'float',
        'night_fee' => 'float',
        'surge_fee' => 'float',
        'priority_fee' => 'float',
        'long_distance_fee' => 'float',
        'pump_earning' => 'float',
        'platform_earning' => 'float',
        'total_amount' => 'float',
        'delivery_lat' => 'float',
        'delivery_lng' => 'float',
        'distance_km' => 'float',
        'booked_distance_km' => 'float',
        'estimated_delivery_minutes' => 'integer',
        'delivery_otp_generated_at' => 'datetime',
        'delivery_otp_verified_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancellation_charge' => 'float',
        'cancellation_charge_paid_at' => 'datetime',
        'agent_last_movement_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (FuelRequest $fuelRequest): void {
            $fuelRequest->syncAcademicProjection();
            $fuelRequest->syncPaymentProjection();
            $fuelRequest->syncLocationProjection();
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function fuelProduct(): BelongsTo { return $this->belongsTo(FuelProduct::class); }
    public function billing(): HasOne { return $this->hasOne(Billing::class, 'order_id'); }
    public function payment(): HasOne { return $this->hasOne(Payment::class, 'fuel_request_id'); }
    public function location(): HasOne { return $this->hasOne(Location::class, 'fuel_request_id'); }
    public function feedback(): HasOne { return $this->hasOne(Feedback::class, 'fuel_request_id'); }

    public function normalizedPaymentMethod(): string
    {
        return match ($this->payment_method) {
            'upi', 'card' => 'online',
            default => (string) $this->payment_method,
        };
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->normalizedPaymentMethod()) {
            'cod' => 'COD',
            'online' => 'Online',
            'wallet' => 'Wallet',
            default => strtoupper((string) $this->payment_method),
        };
    }

    public function canCustomerCancel(): bool
    {
        return ! $this->is_cancelled
            && ! $this->customerCancellationRequiresChargeSettlement()
            && in_array($this->status, self::CUSTOMER_CANCELLABLE_STATUSES, true);
    }

    public function canAgentCancel(): bool
    {
        return ! $this->is_cancelled
            && in_array($this->status, self::AGENT_CANCELLABLE_STATUSES, true);
    }

    public function customerCancellationRequiresChargeSettlement(): bool
    {
        return $this->normalizedPaymentMethod() === 'cod'
            && $this->cancellationChargePaymentIsSettled() === false
            && in_array($this->status, [
                self::STATUS_ACCEPTED,
                self::STATUS_FUEL_PREPARING,
            ], true);
    }

    public function canCustomerPayCancellationCharge(): bool
    {
        return ! $this->is_cancelled
            && $this->customerCancellationRequiresChargeSettlement();
    }

    public function cancellationChargePaymentIsSettled(): bool
    {
        return $this->cancellation_charge_payment_status === self::CANCELLATION_CHARGE_PAYMENT_PAID;
    }

    public function customerCancellationRestrictionMessage(): ?string
    {
        if (! $this->customerCancellationRequiresChargeSettlement()) {
            return null;
        }

        return 'Pay the cancellation fee first, then the COD order will be cancelled.';
    }

    public function customerCancellationChargeAmount(): float
    {
        if (! $this->customerCancellationRequiresChargeSettlement()) {
            return 0.0;
        }

        $billing = $this->relationLoaded('billing')
            ? $this->billing
            : $this->billing()->first();

        return round((float) ($billing?->delivery_charge ?? $this->delivery_charge ?? 0), 2);
    }

    public function cancellationChargePaymentMethodLabel(): ?string
    {
        return match ($this->cancellation_charge_payment_method) {
            self::CANCELLATION_CHARGE_METHOD_WALLET => 'Wallet',
            self::CANCELLATION_CHARGE_METHOD_ONLINE => 'Online',
            default => null,
        };
    }

    public function canForceCancel(): bool
    {
        return ! $this->is_cancelled
            && in_array($this->status, self::FORCE_CANCELLABLE_STATUSES, true);
    }

    public function isActiveLifecycleStatus(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isTerminalStatus(): bool
    {
        return in_array($this->status, [
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
            self::STATUS_REFUND_PROCESSING,
            self::STATUS_COMPLETED_LEGACY,
        ], true);
    }

    public function requiresRefundApproval(): bool
    {
        return $this->is_cancelled
            && $this->status === self::STATUS_REFUND_PROCESSING;
    }

    public function canApproveRefund(): bool
    {
        $billing = $this->relationLoaded('billing')
            ? $this->billing
            : $this->billing()->first();

        return $this->requiresRefundApproval()
            && $billing?->refund_status === Billing::REFUND_PENDING;
    }

    public function statusLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }

    public function displayOrderNumber(): int
    {
        if (! $this->exists || $this->user_id === null) {
            return 0;
        }

        static $cache = [];

        $cacheKey = implode(':', [
            $this->user_id,
            $this->id,
            $this->created_at?->format('Y-m-d H:i:s.u') ?? 'no-created-at',
        ]);

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $query = static::query()
            ->where('user_id', $this->user_id);

        if ($this->created_at !== null) {
            $query->where(function ($builder) {
                $builder->where('created_at', '<', $this->created_at)
                    ->orWhere(function ($sameTime) {
                        $sameTime->where('created_at', $this->created_at)
                            ->where('id', '<=', $this->id);
                    });
            });
        } else {
            $query->where('id', '<=', $this->id);
        }

        return $cache[$cacheKey] = (int) $query->count();
    }

    public function cancelledByLabel(): ?string
    {
        return match ($this->cancelled_by) {
            self::CANCELLED_BY_CUSTOMER => 'Customer',
            self::CANCELLED_BY_AGENT => 'Agent',
            self::CANCELLED_BY_ADMIN => 'Admin',
            self::CANCELLED_BY_SYSTEM => 'System',
            default => null,
        };
    }

    public function cancellationStatusMessage(): string
    {
        $actor = $this->cancelledByLabel();

        if ($actor === null) {
            return 'This order was cancelled.';
        }

        return "{$actor} cancelled this order.";
    }

    public function cancellationReasonMessage(): string
    {
        $reason = trim((string) $this->cancellation_reason);

        if ($reason !== '') {
            return $reason;
        }

        return $this->cancellationStatusMessage();
    }

    public function hasAdditionalCancellationReason(): bool
    {
        $reason = strtolower(trim((string) $this->cancellation_reason));

        if ($reason === '') {
            return false;
        }

        return ! in_array($reason, [
            'cancelled by customer.',
            'cancelled by admin.',
            'cancelled by customer',
            'cancelled by admin',
        ], true);
    }

    public function hasDeliveryCoordinates(): bool {
        return $this->delivery_lat !== null && $this->delivery_lng !== null;
    }

    public function usesLiveCustomerLocation(): bool {
        return $this->location_mode !== 'map_pin';
    }

    public function usesPinnedMapLocation(): bool {
        return $this->location_mode === 'map_pin';
    }

    public function displayLocationLabel(): string
    {
        return $this->usesPinnedMapLocation()
            ? 'Pinned Delivery Address'
            : 'Live Delivery Address';
    }

    public function displayLocationAddress(): string
    {
        $address = trim((string) $this->delivery_address);

        if ($address !== '') {
            return $address;
        }

        return $this->usesPinnedMapLocation()
            ? 'Pinned delivery point selected for background navigation.'
            : 'Live GPS location is being used in the background for navigation.';
    }

    public function hasDeliveryOtp(): bool {
        return $this->delivery_otp !== null && $this->delivery_otp !== '';
    }

    public function hasPendingDeliveryOtp(): bool {
        return $this->status === self::STATUS_OTP_VERIFICATION
            && $this->hasDeliveryOtp()
            && $this->delivery_otp_verified_at === null;
    }

    public function deliveryOtpWasVerified(): bool {
        return $this->delivery_otp_verified_at !== null;
    }

    public function googleMapsDestinationUrl(): ?string {
        if (! $this->hasDeliveryCoordinates()) {
            return null;
        }

        return 'https://www.google.com/maps/search/?' . http_build_query([
            'api' => 1,
            'query' => $this->delivery_lat . ',' . $this->delivery_lng,
        ]);
    }

    public function googleMapsDirectionsUrl(?float $originLat = null, ?float $originLng = null): ?string {
        if (! $this->hasDeliveryCoordinates()) {
            return null;
        }

        $params = [
            'api' => 1,
            'destination' => $this->delivery_lat . ',' . $this->delivery_lng,
            'travelmode' => 'driving',
        ];

        if ($originLat !== null && $originLng !== null) {
            $params['origin'] = $originLat . ',' . $originLng;
        }

        return 'https://www.google.com/maps/dir/?' . http_build_query($params);
    }

    public function historicalDistanceKm(): ?float
    {
        if ($this->booked_distance_km !== null) {
            return (float) $this->booked_distance_km;
        }

        if ($this->distance_km !== null && (float) $this->distance_km > 0.1) {
            return (float) $this->distance_km;
        }

        return $this->inferDistanceFromCharges();
    }

    private function inferDistanceFromCharges(): ?float
    {
        $charge = $this->slab_charge;

        if ($charge === null || (float) $charge <= 0) {
            $charge = (float) $this->delivery_charge
                - (float) $this->handling_fee
                - (float) $this->night_fee
                - (float) $this->surge_fee
                - (float) $this->priority_fee
                - (float) $this->long_distance_fee;
        }

        if ((float) $charge <= 0) {
            return null;
        }

        $distanceKm = $this->distanceFromCurrentSlabs((float) $charge);

        if ($distanceKm !== null) {
            return $distanceKm;
        }

        return $this->distanceFromLegacyCharges((float) $charge);
    }

    private function distanceFromCurrentSlabs(float $charge): ?float
    {
        if (! Schema::hasTable('delivery_slabs')) {
            return null;
        }

        static $slabs = null;

        if ($slabs === null) {
            $slabs = DeliverySlab::query()
                ->orderBy('min_km')
                ->get(['min_km', 'max_km', 'charge']);
        }

        $slab = $slabs->first(function (DeliverySlab $candidate) use ($charge): bool {
            return abs((float) $candidate->charge - $charge) < 0.01;
        });

        if (! $slab) {
            return null;
        }

        return round((((float) $slab->min_km) + ((float) $slab->max_km)) / 2, 2);
    }

    private function distanceFromLegacyCharges(float $charge): ?float
    {
        if (! Schema::hasTable('delivery_charges')) {
            return null;
        }

        static $legacyCharges = null;

        if ($legacyCharges === null) {
            $legacyCharges = DeliveryCharge::query()
                ->orderBy('min_distance_km')
                ->get(['min_distance_km', 'max_distance_km', 'charge_amount']);
        }

        $chargeBand = $legacyCharges->first(function (DeliveryCharge $candidate) use ($charge): bool {
            return abs((float) $candidate->charge_amount - $charge) < 0.01;
        });

        if (! $chargeBand) {
            return null;
        }

        return round((((float) $chargeBand->min_distance_km) + ((float) $chargeBand->max_distance_km)) / 2, 2);
    }

    private function syncAcademicProjection(): void
    {
        if (! Schema::hasColumn($this->getTable(), 'requestId')) {
            return;
        }

        $updates = [];

        if ((int) ($this->getAttribute('requestId') ?? 0) !== (int) $this->getKey()) {
            $updates['requestId'] = $this->getKey();
        }

        if ((int) ($this->getAttribute('userId') ?? 0) !== (int) $this->user_id) {
            $updates['userId'] = $this->user_id;
        }

        $fuelType = $this->resolveFuelType();

        if ($fuelType !== null && $this->getAttribute('fuelType') !== $fuelType) {
            $updates['fuelType'] = $fuelType;
        }

        if ((float) ($this->getAttribute('quantity') ?? 0) !== (float) $this->quantity_liters) {
            $updates['quantity'] = $this->quantity_liters;
        }

        if ($updates !== []) {
            $this->forceFill($updates)->saveQuietly();
        }
    }

    private function syncPaymentProjection(): void
    {
        if (! Schema::hasTable('payments') || $this->user_id === null) {
            return;
        }

        Payment::query()->updateOrCreate(
            ['fuel_request_id' => $this->getKey()],
            [
                'userId' => $this->user_id,
                'amount' => $this->total_amount,
                'paymentmode' => $this->payment_method,
                'status' => $this->payment_status,
            ]
        );
    }

    private function syncLocationProjection(): void
    {
        if (! Schema::hasTable('locations')
            || $this->user_id === null
            || $this->delivery_lat === null
            || $this->delivery_lng === null
            || ! is_string($this->delivery_address)
            || trim($this->delivery_address) === '') {
            return;
        }

        Location::query()->updateOrCreate(
            ['fuel_request_id' => $this->getKey()],
            [
                'userId' => $this->user_id,
                'Latitude' => round((float) $this->delivery_lat, 6),
                'Longitude' => round((float) $this->delivery_lng, 6),
                'Address' => $this->delivery_address,
                'location_mode' => $this->location_mode,
                'captured_at' => $this->updated_at ?? now(),
            ]
        );

        if (Schema::hasColumn('users', 'location')) {
            User::query()
                ->whereKey($this->user_id)
                ->update(['location' => $this->delivery_address]);
        }
    }

    private function resolveFuelType(): ?string
    {
        if (is_string($this->getAttribute('fuelType')) && $this->getAttribute('fuelType') !== '') {
            return $this->getAttribute('fuelType');
        }

        if ($this->relationLoaded('fuelProduct') && $this->fuelProduct) {
            return $this->fuelProduct->fuel_type;
        }

        if ($this->fuel_product_id === null) {
            return null;
        }

        return FuelProduct::query()->whereKey($this->fuel_product_id)->value('fuel_type');
    }
}
