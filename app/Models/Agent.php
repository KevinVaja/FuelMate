<?php
namespace App\Models;

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
        'agentId',
        'name',
        'phone',
        'fuel_availability',
        'address',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'rating' => 'float',
        'wallet_balance' => 'float',
        'current_lat' => 'float',
        'current_lng' => 'float',
        'approved_at' => 'datetime',
        'fuel_availability' => 'float',
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
        return $this->current_lat !== null && $this->current_lng !== null;
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
        ]);
    }

    public function markVerificationRejected(string $reason): void {
        $this->update([
            'verification_status' => self::VERIFICATION_REJECTED,
            'approval_status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_at' => null,
            'is_available' => false,
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
