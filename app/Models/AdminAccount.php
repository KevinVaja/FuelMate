<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAccount extends Model
{
    protected $table = 'admins';

    protected $primaryKey = 'adminId';

    protected $fillable = ['username', 'password', 'email', 'phone', 'user_id', 'wallet_balance'];

    protected $hidden = ['password'];

    protected $casts = [
        'wallet_balance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
