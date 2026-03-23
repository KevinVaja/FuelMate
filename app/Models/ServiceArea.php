<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceArea extends Model {
    protected $fillable = ['name','city','zone','is_active'];
    protected $casts = ['is_active'=>'boolean'];
}
