<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\UserAgentSummary;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Where a user is signed in (browser sessions in the `sessions` table + mobile-app Sanctum tokens) and how to end them.
 *
 * Ending a session also cycles remember_token: otherwise the "remember me" cookie on that device would quietly sign it
 * straight back in. The current device keeps working because its session row stays.
 */
class SessionManager
{
    public const TOKEN_PREFIX = 'token:';

    public function __construct(private AuthEventRecorder $events)
    {
    }

    /** @return Collection<int, object{id:string, device:string, ip:?string, last_active:Carbon, is_current:bool}> newest first */
    public function forUser(User $user, ?string $currentId): Collection
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $user->user_id)
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($row) => (object) [
                'id'          => $row->id,
                'device'      => UserAgentSummary::from($row->user_agent),
                'ip'          => $row->ip_address,
                'last_active' => Carbon::createFromTimestamp((int) $row->last_activity),
                'is_current'  => $currentId !== null && hash_equals((string) $row->id, $currentId),
            ]);

        $tokens = $user->tokens()->get()->map(fn ($token) => (object) [
            'id'          => self::TOKEN_PREFIX.$token->id,
            'device'      => 'Mobile app · '.$token->name,
            'ip'          => null,
            'last_active' => $token->last_used_at ?? $token->created_at,
            'is_current'  => false,
        ]);

        return $sessions->concat($tokens)->sortByDesc(fn ($item) => $item->last_active->getTimestamp())->values();
    }

    /** End one session or one app token of this user. False when it is not theirs (or already gone). */
    public function revokeOne(User $user, string $id): bool
    {
        if (str_starts_with($id, self::TOKEN_PREFIX)) {
            $tokenId = substr($id, strlen(self::TOKEN_PREFIX));
            $deleted = ctype_digit($tokenId) ? $user->tokens()->where('id', (int) $tokenId)->delete() : 0;
        } else {
            $deleted = DB::table('sessions')->where('user_id', $user->user_id)->where('id', $id)->delete();
        }

        if ($deleted === 0) {
            return false;
        }

        $this->cycleRememberToken($user);
        $this->events->record('sessions_revoked', $user, metadata: ['count' => 1]);

        return true;
    }

    /** Sign out every device except the one holding $keepSessionId. Returns how many sessions and app tokens ended. */
    public function revokeOthers(User $user, string $keepSessionId): int
    {
        $count = DB::table('sessions')
            ->where('user_id', $user->user_id)
            ->where('id', '!=', $keepSessionId)
            ->delete();
        $count += $user->tokens()->delete();

        $this->cycleRememberToken($user);
        $this->events->record('sessions_revoked', $user, metadata: ['count' => $count]);

        return $count;
    }

    /** Admin action: end every session and token of the user, including the one they are on right now. Audited. */
    public function revokeAll(User $user): int
    {
        $count = DB::transaction(function () use ($user) {
            $count = DB::table('sessions')->where('user_id', $user->user_id)->delete();
            $count += $user->tokens()->delete();
            $this->cycleRememberToken($user);

            AuditLog::record('user.sign_out_everywhere', "Signed out {$user->email} everywhere.", $user, metadata: ['count' => $count]);

            return $count;
        });

        $this->events->record('sessions_revoked', $user, metadata: ['count' => $count, 'by_admin' => true]);

        return $count;
    }

    private function cycleRememberToken(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }
}
