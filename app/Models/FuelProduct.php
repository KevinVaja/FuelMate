<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelProduct extends Model {
    protected $fillable = ['name','fuel_type','price_per_liter','is_available','description'];
    protected $casts = ['is_available'=>'boolean','price_per_liter'=>'float'];

    public function fuelRequests(): HasMany { return $this->hasMany(FuelRequest::class); }
}
