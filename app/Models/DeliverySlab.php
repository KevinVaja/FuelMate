<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliverySlab extends Model
{
    protected $fillable = ['min_km', 'max_km', 'charge'];

    protected $casts = [
        'min_km' => 'float',
        'max_km' => 'float',
        'charge' => 'float',
    ];
}
