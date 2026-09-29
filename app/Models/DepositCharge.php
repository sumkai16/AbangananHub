<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositCharge extends Model
{
    protected $primaryKey = 'deposit_charge_id';

    protected $fillable = [
        'reservation_id',
        'amount',
        'category',
        'description',
        'charged_at',
        'charged_by',
        'voided_at',
        'voided_by',
        'void_reason',
        'void_note',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'charged_at' => 'date',
        'voided_at'  => 'datetime',
    ];

    /** What a deposit charge is for. Drives the category select on the "Add charge" form. */
    public const CATEGORIES = [
        'Damage'         => 'Damage',
        'Cleaning'       => 'Cleaning',
        'Missing Item'   => 'Missing item',
        'Unpaid Utility' => 'Unpaid utility',
        'Other'          => 'Other',
    ];

    /** Named causes a recorded charge can be voided for. Keys are the enum members. */
    public const VOID_REASONS = [
        'wrong_amount'   => 'Wrong amount entered',
        'wrong_tenancy'  => 'Recorded against the wrong tenant',
        'not_applicable' => 'Charge does not apply after all',
        'duplicate'      => 'Duplicate entry',
        'other'          => 'Other',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function charger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'charged_by', 'user_id');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by', 'user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function voidReasonLabel(): ?string
    {
        return $this->void_reason ? (self::VOID_REASONS[$this->void_reason] ?? $this->void_reason) : null;
    }
}
