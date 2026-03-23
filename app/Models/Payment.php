<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $primaryKey = 'paymentId';

    protected $fillable = ['userId', 'amount', 'paymentmode', 'status', 'fuel_request_id'];

    protected $casts = [
        'amount' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId', 'userId');
    }

    public function fuelRequest(): BelongsTo
    {
        return $this->belongsTo(FuelRequest::class, 'fuel_request_id');
    }
}
