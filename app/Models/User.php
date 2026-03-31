<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class User extends Authenticatable {
    protected $fillable = ['name','email','email_verified_at','phone','password','role','status','location','wallet_balance'];
    protected $hidden = ['password','remember_token'];
    protected $casts = ['email_verified_at'=>'datetime','password'=>'hashed','wallet_balance'=>'decimal:2'];

    protected static function booted(): void
    {
        static::saved(function (User $user): void {
            $user->syncAcademicProjection();
        });
    }

    public function agent(): HasOne { return $this->hasOne(Agent::class); }
    public function adminAccount(): HasOne { return $this->hasOne(AdminAccount::class); }
    public function fuelRequests(): HasMany { return $this->hasMany(FuelRequest::class); }
    public function supportTickets(): HasMany { return $this->hasMany(SupportTicket::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class, 'userId', 'userId'); }
    public function locations(): HasMany { return $this->hasMany(Location::class, 'userId', 'userId'); }
    public function feedbackEntries(): HasMany { return $this->hasMany(Feedback::class, 'userId', 'userId'); }
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isAgent(): bool { return $this->role === 'agent'; }
    public function isUser(): bool { return $this->role === 'user'; }

    public function syncAcademicProjection(): void
    {
        if (! Schema::hasColumn($this->getTable(), 'userId')) {
            return;
        }

        if ((int) ($this->getAttribute('userId') ?? 0) !== (int) $this->getKey()) {
            $this->forceFill(['userId' => $this->getKey()])->saveQuietly();
        }

        $this->syncAdminProjection();
    }

    private function syncAdminProjection(): void
    {
        if (! Schema::hasTable('admins') || ! $this->isAdmin()) {
            return;
        }

        $username = Str::limit(
            Str::before((string) $this->email, '@') ?: Str::slug((string) $this->name, ''),
            50,
            ''
        );

        $this->adminAccount()->updateOrCreate(
            ['user_id' => $this->getKey()],
            [
                'username' => $username !== '' ? $username : 'admin' . $this->getKey(),
                'password' => $this->password,
                'email' => $this->email,
                'phone' => $this->phone,
            ]
        );
    }
}
