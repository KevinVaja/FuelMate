<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    protected $primaryKey = 'locationId';

    protected $fillable = ['userId', 'Latitude', 'Longitude', 'Address', 'fuel_request_id', 'location_mode', 'captured_at'];

    protected $casts = [
        'Latitude' => 'float',
        'Longitude' => 'float',
        'captured_at' => 'datetime',
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
