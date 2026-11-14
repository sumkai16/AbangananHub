<?php

namespace App\Services\Security;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Tamper evidence for audit_logs. Each row stores the previous row's hash (prev_hash) and its own HMAC
 * (row_hash) over its content. Editing a row, or deleting one, breaks the chain at that point, and
 * `audit:verify` finds it. This detects direct database edits; it cannot stop them.
 */
class AuditChain
{
    /** prev_hash of the very first row. */
    public static function genesis(): string
    {
        return str_repeat('0', 64);
    }

    /** Canonical JSON of the fields a row's hash covers. Key order of `metadata` never matters. */
    public static function payload(array $row): string
    {
        $metadata = $row['metadata'] ?? null;
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }
        $metadata = is_array($metadata) && $metadata !== [] ? self::ksortRecursive($metadata) : null;

        $createdAt = $row['created_at'] ?? null;
        if ($createdAt instanceof CarbonInterface) {
            $createdAt = $createdAt->format('Y-m-d H:i:s');
        } elseif ($createdAt !== null) {
            $createdAt = (string) $createdAt;
        }

        $actorId = $row['actor_id'] ?? null;
        $auditableId = $row['auditable_id'] ?? null;

        return json_encode([
            'prev_hash'      => $row['prev_hash'] ?? self::genesis(),
            'actor_id'       => $actorId === null ? null : (int) $actorId,
            'actor_name'     => $row['actor_name'] ?? null,
            'actor_email'    => $row['actor_email'] ?? null,
            'action'         => $row['action'] ?? null,
            'auditable_type' => $row['auditable_type'] ?? null,
            'auditable_id'   => $auditableId === null ? null : (int) $auditableId,
            'summary'        => $row['summary'] ?? null,
            'reason'         => $row['reason'] ?? null,
            'metadata'       => $metadata,
            'ip_address'     => $row['ip_address'] ?? null,
            'created_at'     => $createdAt,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function hash(array $row): string
    {
        return hash_hmac('sha256', self::payload($row), self::key());
    }

    /**
     * Walk the whole log in order. `broken_at` is the log_id of the first row that no longer matches
     * (a deleted row shows up as the row after it). Every row must carry a hash: the migration hashed all existing rows, so a null hash means someone cleared it.
     *
     * @return array{ok: bool, checked: int, broken_at: ?int}
     */
    public static function verify(): array
    {
        $checked = 0;
        $expectedPrev = null; // null until the first row; the genesis value stands in for it
        $brokenAt = null;

        DB::table('audit_logs')->orderBy('log_id')->chunkById(1000, function ($rows) use (&$checked, &$expectedPrev, &$brokenAt) {
            foreach ($rows as $row) {
                $row = (array) $row;

                $prevOk = $row['prev_hash'] === ($expectedPrev ?? self::genesis());
                $hashOk = $row['row_hash'] !== null && hash_equals(self::hash($row), (string) $row['row_hash']);

                if (! $prevOk || ! $hashOk) {
                    $brokenAt = (int) $row['log_id'];

                    return false;
                }

                $expectedPrev = $row['row_hash'];
                $checked++;
            }
        }, 'log_id');

        return ['ok' => $brokenAt === null, 'checked' => $checked, 'broken_at' => $brokenAt];
    }

    private static function key(): string
    {
        return (string) (config('security.audit_chain_key') ?: config('app.key'));
    }

    private static function ksortRecursive(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::ksortRecursive($v);
            }
        }
        ksort($data);

        return $data;
    }
}
