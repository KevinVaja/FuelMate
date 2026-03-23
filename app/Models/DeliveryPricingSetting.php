<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryPricingSetting extends Model
{
    protected $fillable = [
        'surge_enabled',
        'night_delivery_enabled',
        'night_delivery_fee',
        'night_starts_at',
        'night_ends_at',
    ];

    protected $casts = [
        'surge_enabled' => 'boolean',
        'night_delivery_enabled' => 'boolean',
        'night_delivery_fee' => 'decimal:2',
    ];
}
