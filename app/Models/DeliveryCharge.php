<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryCharge extends Model {
    protected $fillable = ['min_distance_km','max_distance_km','charge_amount','is_active'];
    protected $casts = ['min_distance_km'=>'float','max_distance_km'=>'float','charge_amount'=>'float','is_active'=>'boolean'];
}
