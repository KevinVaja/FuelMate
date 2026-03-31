<?php
namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Agent extends Model {
    public const VERIFICATION_PENDING = 'pending';
    public const VERIFICATION_APPROVED = 'approved';
    public const VERIFICATION_REJECTED = 'rejected';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_BUSY = 'busy';
    public const STATUS_OFFLINE = 'offline';
    public const HEARTBEAT_WINDOW_MINUTES = 15;
    public const ACTIVE_DELIVERY_STATUSES = [
        FuelRequest::STATUS_ACCEPTED,
        FuelRequest::STATUS_FUEL_PREPARING,
        FuelRequest::STATUS_ON_THE_WAY,
        FuelRequest::STATUS_ARRIVED,
        FuelRequest::STATUS_OTP_VERIFICATION,
    ];

    protected $fillable = [
        'user_id',
        'vehicle_type',
        'vehicle_license_plate',
        'approval_status',
        'petrol_license_photo',
        'gst_certificate_photo',
        'owner_id_proof_photo',
        'verification_status',
        'rejection_reason',
        'approved_at',
        'is_available',
        'rating',
        'total_deliveries',
        'wallet_balance',
        'current_lat',
        'current_lng',
        'current_latitude',
        'current_longitude',
        'last_location_update',
        'agentId',
        'name',
        'phone',
        'fuel_availability',
        'address',
        'status',
        'last_active_at',
        'pump_latitude',
        'pump_longitude',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'rating' => 'float',
        'wallet_balance' => 'float',
        'current_lat' => 'float',
        'current_lng' => 'float',
        'current_latitude' => 'float',
        'current_longitude' => 'float',
        'approved_at' => 'datetime',
        'fuel_availability' => 'float',
        'last_location_update' => 'datetime',
        'last_active_at' => 'datetime',
        'pump_latitude' => 'float',
        'pump_longitude' => 'float',
    ];

    protected static function booted(): void
    {
        static::saved(function (Agent $agent): void {
            $agent->syncAcademicProjection();
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function fuelRequests(): HasMany { return $this->hasMany(FuelRequest::class); }
    public function withdrawals(): HasMany { return $this->hasMany(AgentWithdrawal::class); }

    public function scopePendingVerification(Builder $query): Builder {
        return $query->where('verification_status', self::VERIFICATION_PENDING);
    }

    public function scopeApprovedForOperations(Builder $query): Builder {
        return $query
            ->where('approval_status', 'approved')
            ->where('verification_status', self::VERIFICATION_APPROVED);
    }

    public function hasLiveLocation(): bool {
        return $this->trackingLatitude() !== null && $this->trackingLongitude() !== null;
    }

    public function trackingLatitude(): ?float
    {
        return $this->current_latitude ?? $this->current_lat;
    }

    public function trackingLongitude(): ?float
    {
        return $this->current_longitude ?? $this->current_lng;
    }

    public function hasRecentLocationUpdate(): bool
    {
        return $this->last_location_update !== null;
    }

    public function updateTrackingLocation(float $latitude, float $longitude): void
    {
        $this->forceFill([
            'current_lat' => $latitude,
            'current_lng' => $longitude,
            'current_latitude' => $latitude,
            'current_longitude' => $longitude,
            'last_location_update' => now(),
        ])->save();
    }

    public function coverageLatitude(): ?float
    {
        return $this->pump_latitude ?? $this->current_lat;
    }

    public function coverageLongitude(): ?float
    {
        return $this->pump_longitude ?? $this->current_lng;
    }

    public function hasCoverageCenter(): bool
    {
        return $this->coverageLatitude() !== null && $this->coverageLongitude() !== null;
    }

    public function liveLatitude(): ?float
    {
        return $this->trackingLatitude() ?? $this->coverageLatitude();
    }

    public function liveLongitude(): ?float
    {
        return $this->trackingLongitude() ?? $this->coverageLongitude();
    }

    public function lastSeenAt(): ?CarbonInterface
    {
        return $this->last_location_update ?? $this->last_active_at ?? $this->updated_at;
    }

    public function isRecentlyActive(): bool
    {
        $lastSeenAt = $this->lastSeenAt();

        return $lastSeenAt !== null
            && $lastSeenAt->gte(now()->subMinutes(self::HEARTBEAT_WINDOW_MINUTES));
    }

    public function computedMonitorStatus(?bool $hasActiveDelivery = null): string
    {
        if ($hasActiveDelivery ?? $this->fuelRequests()
            ->whereIn('status', self::ACTIVE_DELIVERY_STATUSES)
            ->exists()) {
            return self::STATUS_BUSY;
        }

        if ($this->isApprovedForOperations() && $this->is_available && $this->isRecentlyActive()) {
            return self::STATUS_ACTIVE;
        }

        return self::STATUS_OFFLINE;
    }

    public function syncPresence(bool $touchHeartbeat = true, ?bool $hasActiveDelivery = null): void
    {
        $attributes = [];

        if ($touchHeartbeat) {
            $attributes['last_active_at'] = now();
            $this->last_active_at = $attributes['last_active_at'];
        }

        if ($this->pump_latitude === null && $this->trackingLatitude() !== null) {
            $attributes['pump_latitude'] = $this->trackingLatitude();
        }

        if ($this->pump_longitude === null && $this->trackingLongitude() !== null) {
            $attributes['pump_longitude'] = $this->trackingLongitude();
        }

        $status = $this->computedMonitorStatus($hasActiveDelivery);
        if ($this->status !== $status) {
            $attributes['status'] = $status;
        }

        if ($attributes === []) {
            return;
        }

        $this->forceFill($attributes);
        $this->saveQuietly();
    }

    public function markOffline(): void
    {
        $this->forceFill([
            'status' => self::STATUS_OFFLINE,
            'last_active_at' => now()->subMinutes(self::HEARTBEAT_WINDOW_MINUTES + 1),
        ])->saveQuietly();
    }

    public function isApprovedForOperations(): bool {
        return $this->approval_status === 'approved'
            && $this->verification_status === self::VERIFICATION_APPROVED;
    }

    public function markVerificationApproved(): void {
        $this->update([
            'verification_status' => self::VERIFICATION_APPROVED,
            'approval_status' => 'approved',
            'rejection_reason' => null,
            'approved_at' => now(),
            'status' => self::STATUS_OFFLINE,
        ]);
    }

    public function markVerificationRejected(string $reason): void {
        $this->update([
            'verification_status' => self::VERIFICATION_REJECTED,
            'approval_status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_at' => null,
            'is_available' => false,
            'status' => self::STATUS_OFFLINE,
        ]);
    }

    public function documentUrl(string $attribute): ?string {
        $path = $this->getAttribute($attribute);

        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function documentIsPdf(string $attribute): bool {
        $path = $this->getAttribute($attribute);

        if (! is_string($path) || $path === '') {
            return false;
        }

        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function syncAcademicProjection(): void
    {
        if (! Schema::hasColumn($this->getTable(), 'agentId')) {
            return;
        }

        $user = $this->relationLoaded('user')
            ? $this->user
            : $this->user()->first();

        $phone = $user?->phone;

        if ($phone !== null
            && static::query()->whereKeyNot($this->getKey())->where('phone', $phone)->exists()) {
            $phone = null;
        }

        $address = $this->getAttribute('address');

        if (! is_string($address) || trim($address) === '') {
            $address = $this->fuelRequests()
                ->whereNotNull('delivery_address')
                ->latest('updated_at')
                ->value('delivery_address') ?: 'Address not provided';
        }

        $this->forceFill([
            'agentId' => $this->getKey(),
            'name' => $user?->name ?? $this->getAttribute('name'),
            'phone' => $phone,
            'fuel_availability' => $this->is_available ? 1 : 0,
            'address' => $address,
        ])->saveQuietly();
    }
}
