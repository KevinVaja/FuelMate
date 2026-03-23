<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Billing extends Model
{
    public const STATUS_ESTIMATED = 'estimated';
    public const STATUS_FINAL = 'final';
    public const STATUS_PAID = 'paid';
    public const STATUS_SETTLED = 'settled';

    public const REFUND_NONE = 'none';
    public const REFUND_PENDING = 'pending';
    public const REFUND_APPROVED = 'approved';
    public const REFUND_REFUNDED = 'refunded';

    public const SETTLEMENT_PENDING = 'pending';
    public const SETTLEMENT_APPROVED = 'approved';
    public const SETTLEMENT_PAID_OUT = 'paid_out';

    protected $fillable = [
        'order_id',
        'billing_status',
        'fuel_price_per_liter',
        'fuel_quantity',
        'fuel_total',
        'delivery_charge',
        'platform_fee',
        'gst_percent',
        'gst_amount',
        'total_amount',
        'agent_commission_percent',
        'agent_earning',
        'admin_commission',
        'coupon_discount',
        'surge_pricing_multiplier',
        'refundable_amount',
        'refunded_amount',
        'refund_status',
        'refund_processed_at',
        'settlement_status',
        'payment_reference',
        'payment_gateway',
        'payment_webhook_payload',
    ];

    protected $casts = [
        'fuel_price_per_liter' => 'decimal:2',
        'fuel_quantity' => 'decimal:2',
        'fuel_total' => 'decimal:2',
        'delivery_charge' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'gst_percent' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'agent_commission_percent' => 'decimal:2',
        'agent_earning' => 'decimal:2',
        'admin_commission' => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'surge_pricing_multiplier' => 'decimal:2',
        'refundable_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'refund_processed_at' => 'datetime',
        'payment_webhook_payload' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(FuelRequest::class, 'order_id');
    }
}
