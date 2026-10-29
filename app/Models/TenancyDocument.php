<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One requirement of a lease for one tenancy, and the file the tenant uploaded for it. The file sits on the
 * private disk and is served only through policy-gated routes. See App\Services\TenancyDocuments for every rule.
 */
class TenancyDocument extends Model
{
    protected $primaryKey = 'tenancy_document_id';

    public const STATUSES = ['Requested', 'Pending', 'Approved', 'Rejected', 'Waived'];

    /** Upload types and size, shared by the controller's validation. */
    public const MIMES = 'pdf,jpg,jpeg,png,webp';

    public const MAX_KB = 10240;

    protected $fillable = [
        'reservation_id', 'doc_key', 'label', 'status', 'file_path', 'file_name', 'mime', 'size',
        'rejection_reason', 'uploaded_at', 'reviewed_by', 'reviewed_at', 'purge_after', 'purged_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'purge_after' => 'date',
            'purged_at' => 'datetime',
            'size' => 'integer',
        ];
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null && $this->purged_at === null;
    }

    /** Needs the tenant's action: nothing uploaded yet, or the landlord sent it back. */
    public function isOutstanding(): bool
    {
        return in_array($this->status, ['Requested', 'Rejected'], true);
    }

    /** The tenant may still change the file. Approved and waived rows are settled. */
    public function tenantCanReplace(): bool
    {
        return in_array($this->status, ['Requested', 'Pending', 'Rejected'], true);
    }
}
