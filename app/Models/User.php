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
        'is_walk_in',
        'created_by_landlord_id',
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
            'is_walk_in' => 'boolean',
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

    // ─── Rating summaries ────────────────────────────────────
    // A user gets two independent scores that measure different things from
    // different raters, so they are never blended: what tenants rated them as a
    // landlord (property reviews rolled up), and what landlords rated them as a
    // tenant. avg is null (not 0.0) when there are no ratings — a fake zero
    // reads as a terrible score rather than "unrated".

    /**
     * This user's rating as a landlord: the average of the reviews tenants left
     * on their properties. Hidden reviews are excluded, matching every other
     * landlord-rating display (Landlord\ProfileController, Property::withAvg).
     */
    public function landlordRatingSummary(): array
    {
        $row = Review::whereIn('property_id', $this->properties()->select('property_id'))
            ->where('is_hidden', false)
            ->selectRaw('AVG(rating) as avg, COUNT(*) as c')
            ->first();

        return [
            'avg'   => $row->c > 0 ? round((float) $row->avg, 1) : null,
            'count' => (int) $row->c,
        ];
    }

    /**
     * This user's rating as a tenant: the average of the tenant_ratings
     * landlords left about them. The only place this score surfaces — it was
     * collected but never shown before the Overall Ratings feature.
     */
    public function tenantRatingSummary(): array
    {
        $row = $this->tenantRatingsReceived()
            ->selectRaw('AVG(rating) as avg, COUNT(*) as c')
            ->first();

        return [
            'avg'   => $row->c > 0 ? round((float) $row->avg, 1) : null,
            'count' => (int) $row->c,
        ];
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

    /**
     * True when this user sees the landlord sidebar shell rather than the
     * public one. Admins are excluded deliberately: they keep browsing the
     * public site through `layouts.app` even though they also have a shell
     * of their own.
     *
     * Several views pick their layout at runtime from this condition
     * (conversations, agreements, tenant reservations). It lives here so the
     * layout choice and the page width can't drift apart — see
     * `shellContainerClass()`.
     */
    public function usesLandlordShell(): bool
    {
        return $this->hasRole('Landlord') && ! $this->hasRole('Admin');
    }

    /**
     * The page-container width for whichever shell this user is rendering in.
     * Sidebar shells lose 256px to the nav, so they get the wider work-area
     * cap; the public shell uses the full viewport and stays narrower.
     * See DESIGN.md §5.
     *
     * `$inSidebarShell` lets a view pass its own layout condition when it
     * differs from the landlord default (e.g. profile/edit keys off Admin,
     * landlord/profile/show keys off ownership).
     */
    public function shellContainerClass(?bool $inSidebarShell = null): string
    {
        $sidebar = $inSidebarShell ?? $this->usesLandlordShell();

        return $sidebar ? 'max-w-[1600px]' : 'max-w-[1400px]';
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

    /**
     * Tenants this landlord entered by hand through the walk-in flow.
     *
     * Lets the walk-in form offer a tenant the landlord has already recorded
     * (someone moving between units, or renewing) instead of making them
     * retype the details and creating a duplicate person.
     */
    public function walkInTenants(): HasMany
    {
        return $this->hasMany(User::class, 'created_by_landlord_id', 'user_id')
            ->where('is_walk_in', true);
    }

    /**
     * A landlord-entered tenant rather than someone who registered.
     *
     * Nothing about a walk-in is platform-verified — the landlord asserted all
     * of it — so anywhere this user appears beside a registered tenant, it has
     * to be badged as such.
     */
    public function isWalkIn(): bool
    {
        return (bool) $this->is_walk_in;
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