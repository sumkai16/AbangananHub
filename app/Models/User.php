<?php

namespace App\Models;

use App\Mail\PasswordResetMail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable
{
use HasApiTokens, HasFactory, Notifiable;
protected $primaryKey = 'user_id';

    public function getRouteKeyName(): string
    {
        return 'user_id';
    }
   protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'contact_number',
        'profile_picture',
        'account_status',
        'bio',
        'profile_visibility',
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

    public function verificationApplication()
    {
        return $this->hasOne(LandlordVerification::class, 'user_id', 'user_id');
    }
    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'user_id', 'user_id');
    }
    public function properties()
    {
        return $this->hasMany(Property::class, 'landlord_id', 'user_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'tenant_id', 'user_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'tenant_id', 'user_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'tenant_id', 'user_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id', 'user_id');
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'reporter_id', 'user_id');
    }

    public function tenantConversations()
    {
        return $this->hasMany(Conversation::class, 'tenant_id', 'user_id');
    }

    public function landlordConversations()
    {
        return $this->hasMany(Conversation::class, 'landlord_id', 'user_id');
    }
public function tenantRatingsReceived()
    {
        return $this->hasMany(TenantRating::class, 'tenant_id', 'user_id');
    }

    public function tenantRatingsGiven()
    {
        return $this->hasMany(TenantRating::class, 'landlord_id', 'user_id');
    }
    // ─── Role Helpers ────────────────────────────────────────

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('role', $role)->exists();
    }

    /**
     * Where this user belongs after logging in, registering, or verifying email.
     * Landlords and admins manage things, so they get a dashboard; tenants
     * browse, so they go to the listings. Brand-new accounts have no role yet
     * (Tenant is granted on first browse) and fall through to the same place.
     *
     * This is the single source of truth for post-auth destinations — every
     * auth controller calls it, so login and registration can't drift apart.
     */
    public function homeRoute(): string
    {
        return match (true) {
            $this->hasRole('Admin')    => route('admin.dashboard'),
            $this->hasRole('Landlord') => route('landlord.dashboard'),
            default                    => route('properties.index'),
        };
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
    public function rentalBusiness()
    {
        return $this->hasOne(RentalBusiness::class, 'landlord_id', 'user_id');
    }

    // ─── Password Reset ──────────────────────────────────────

    public function sendPasswordResetNotification($token): void
    {
        Mail::to($this->email)->send(new PasswordResetMail($this, $token));
    }
}