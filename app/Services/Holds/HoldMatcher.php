<?php

namespace App\Services\Holds;

use App\Models\AuditLog;
use App\Models\AuthEvent;
use App\Models\HoldMatchAlert;
use App\Models\Notification;
use App\Models\TenantHold;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Notices when a new account (or a sign-in from a new browser) looks like a tenant who is on hold, and asks an admin to
 * look. It never blocks anything and tells the person nothing. Compared against ACTIVE holds only, from data the
 * platform already keeps: the user's name and phone, and auth_events (browser id, IP). The rule lives in
 * config/security.php `hold_match`: phone or browser alone alert, name + IP together, never IP or name alone (shared
 * Wi-Fi, common names). See plans/tenant-payment-holds.md.
 */
class HoldMatcher
{
    /**
     * Fire-and-forget for the sign-up and sign-in hooks: runs after the response and swallows every failure, so
     * a matcher problem can never break a sign-up or a login.
     */
    public static function inspectAfterResponse(User $user, ?string $deviceId, ?string $ip, string $source): void
    {
        try {
            // Cheap check first: with no active hold there is nothing to compare against.
            if (! TenantHold::active()->exists()) {
                return;
            }

            defer(function () use ($user, $deviceId, $ip, $source) {
                try {
                    app(self::class)->inspect($user, $deviceId, $ip, $source);
                } catch (Throwable $e) {
                    Log::warning('Could not check for a duplicate of a held tenant', ['error' => $e->getMessage()]);
                }
            });
        } catch (Throwable $e) {
            Log::warning('Could not check for a duplicate of a held tenant', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Compare $user with every tenant on an active hold.
     *
     * @return Collection<int, HoldMatchAlert> the alerts created by this call (not ones that already existed)
     */
    public function inspect(User $user, ?string $deviceId = null, ?string $ip = null, string $source = 'signup'): Collection
    {
        $created = collect();

        $holds = TenantHold::active()->where('tenant_id', '!=', $user->user_id)->with('tenant')->get();

        foreach ($holds as $hold) {
            $signals = $this->signals($user, $hold->tenant, $deviceId, $ip);

            if (! $this->shouldAlert($signals)) {
                continue;
            }

            $alert = HoldMatchAlert::where('hold_id', $hold->hold_id)->where('user_id', $user->user_id)->first();

            if ($alert) {
                // Already raised: fold in any new signal quietly. A dismissed alert stays dismissed.
                $merged = array_values(array_unique(array_merge($alert->signals ?? [], $signals)));
                if ($merged !== ($alert->signals ?? [])) {
                    $alert->update(['signals' => $merged]);
                }
                continue;
            }

            $alert = $this->raise($hold, $user, $signals, $source);
            if ($alert) {
                $created->push($alert);
            }
        }

        return $created;
    }

    /** The admin says this is a different person. */
    public function dismiss(HoldMatchAlert $alert, User $admin): HoldMatchAlert
    {
        return DB::transaction(function () use ($alert, $admin) {
            $alert->update([
                'status'      => HoldMatchAlert::DISMISSED,
                'reviewed_by' => $admin->user_id,
                'reviewed_at' => now(),
            ]);

            AuditLog::record(
                'hold.duplicate_dismissed',
                "Dismissed the duplicate-account alert for {$alert->user->email}.",
                $alert,
                metadata: ['signals' => $alert->signals],
            );

            return $alert;
        });
    }

    /**
     * The admin decides the new account is the same person: it gets its own active hold, linked to the original so it
     * lifts when that one does (HoldService handles the lifting). Nothing here names the original hold or person.
     */
    public function putOnHold(HoldMatchAlert $alert, User $admin): TenantHold
    {
        return DB::transaction(function () use ($alert, $admin) {
            $user = $alert->user;

            $existing = TenantHold::open()
                ->where('tenant_id', $user->user_id)
                ->where('source_hold_id', $alert->hold_id)
                ->first();

            $copy = $existing ?? TenantHold::create([
                'tenant_id'      => $user->user_id,
                'landlord_id'    => null,
                'reservation_id' => null,
                'source_hold_id' => $alert->hold_id,
                'amount_owed'    => 0,
                'reason'         => 'Possible duplicate account of a tenant with an unpaid-rent hold.',
                'status'         => TenantHold::ACTIVE,
                'flagged_at'     => now(),
                'confirmed_by'   => $admin->user_id,
                'confirmed_at'   => now(),
            ]);

            $alert->update([
                'status'      => HoldMatchAlert::HELD,
                'reviewed_by' => $admin->user_id,
                'reviewed_at' => now(),
            ]);

            AuditLog::record(
                'hold.duplicate_held',
                "Put {$user->email} on hold as a possible duplicate account.",
                $copy,
                metadata: ['signals' => $alert->signals, 'source_hold_id' => $alert->hold_id],
            );

            Notification::notify(
                $user->user_id,
                'account',
                'Some actions on your account are paused',
                'AbangananHub is reviewing your account. You can still sign in and pay any rent you owe. Please contact support if you think this is a mistake.',
                null,
                null,
                $copy,
            );

            return $copy;
        });
    }

    /** @return list<string> which of phone, device, name, ip match $held. */
    private function signals(User $user, User $held, ?string $deviceId, ?string $ip): array
    {
        $signals = [];

        $phone = $this->phoneKey($user->contact_number);
        if ($phone !== null && $phone === $this->phoneKey($held->contact_number)) {
            $signals[] = 'phone';
        }

        if ($deviceId && AuthEvent::where('user_id', $held->user_id)->where('device_id', $deviceId)->exists()) {
            $signals[] = 'device';
        }

        $name = $this->nameKey($user);
        if ($name !== '' && $name === $this->nameKey($held)) {
            $signals[] = 'name';
        }

        if ($ip && AuthEvent::where('user_id', $held->user_id)->where('ip_address', $ip)->exists()) {
            $signals[] = 'ip';
        }

        return $signals;
    }

    /** Alert when one signal from `alone` matched, or every signal of one `together` group did. */
    private function shouldAlert(array $signals): bool
    {
        if (array_intersect((array) config('security.hold_match.alone', []), $signals)) {
            return true;
        }

        foreach ((array) config('security.hold_match.together', []) as $group) {
            if ($group && ! array_diff($group, $signals)) {
                return true;
            }
        }

        return false;
    }

    /** Digits only, last 10, so 0917…, +63917… and 63917… all compare equal. Null when too short to mean anything. */
    private function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) >= 7 ? substr($digits, -10) : null;
    }

    /** First + last name, lower-cased, accents and anything but letters removed. */
    private function nameKey(User $user): string
    {
        return preg_replace('/[^a-z]+/', '', Str::lower(Str::ascii($user->first_name.$user->last_name)));
    }

    private function raise(TenantHold $hold, User $user, array $signals, string $source): ?HoldMatchAlert
    {
        try {
            $alert = HoldMatchAlert::create([
                'hold_id' => $hold->hold_id,
                'user_id' => $user->user_id,
                'signals' => $signals,
                'status'  => HoldMatchAlert::OPEN,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Two requests raised the same alert at once; the other one notified.
            return null;
        }

        $words = collect($signals)->map(fn ($s) => Str::lower(HoldMatchAlert::SIGNALS[$s] ?? $s))->implode(', ');

        AuditLog::record(
            'hold.duplicate_flagged',
            "Account {$user->email} looks like a tenant on hold.",
            $alert,
            metadata: ['signals' => $signals, 'source' => $source],
            asSystem: true,
        );

        $admins = User::whereHas('roles', fn ($q) => $q->where('role', 'Admin'))->pluck('user_id');
        foreach ($admins as $adminId) {
            Notification::notify(
                (int) $adminId,
                'account',
                'Possible duplicate account',
                "A new account may belong to a tenant on hold ({$words}). Please review it.",
                '/admin/tenant-holds?tab=duplicates',
            );
        }

        // The landlord hears that a duplicate was found, and nothing about who or where.
        if ($hold->landlord_id) {
            $tenant = $hold->tenant;
            Notification::notify(
                (int) $hold->landlord_id,
                'account',
                'Possible duplicate account',
                'A possible duplicate account of '.trim($tenant->first_name.' '.$tenant->last_name).' was found. An admin is reviewing it.',
            );
        }

        return $alert;
    }
}
