<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model {
    protected $fillable = ['user_id','subject','message','status','admin_response'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
