<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $table = 'feedback';

    protected $primaryKey = 'feedbackId';

    protected $fillable = ['userId', 'comments', 'rating', 'fuel_request_id'];

    protected $casts = [
        'rating' => 'integer',
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
