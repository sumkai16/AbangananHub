<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
class User extends Authenticatable
{
    use HasFactory, Notifiable;
protected $primaryKey = 'user_id';
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'contact_number',
        'profile_picture',
        'account_status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ─── Relationships ───────────────────────────────────────

    public function roles()
    {
        return $this->hasMany(UserRole::class);
    }

    public function verificationApplication()
    {
        return $this->hasOne(LandlordVerification::class);
    }

    public function properties()
    {
        return $this->hasMany(Property::class, 'landlord_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'tenant_id', 'user_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'tenant_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'tenant_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function tenantConversations()
    {
        return $this->hasMany(Conversation::class, 'tenant_id');
    }

    public function landlordConversations()
    {
        return $this->hasMany(Conversation::class, 'landlord_id');
    }

    // ─── Role Helpers ────────────────────────────────────────

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('role', $role)->exists();
    }

    // Provide a computed `name` attribute when the DB column is removed.
    public function getNameAttribute($value)
    {
        if (!empty($value)) {
            return $value;
        }

        $first = $this->first_name ?? '';
        $last = $this->last_name ?? '';
        $full = trim($first . ' ' . $last);

        return $full !== '' ? $full : ($this->email ?? '');
    }

    public function assignRole(string $role): void
    {
        if (!$this->hasRole($role)) {
            $this->roles()->create(['role' => $role]);
        }
    }
}