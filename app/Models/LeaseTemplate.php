<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A named lease a landlord writes once and assigns to properties or units (plans/lease-templates-page.md).
 * Every rule is optional: null falls back to the property, then the platform default. See LeaseTerms::snapshot().
 */
class LeaseTemplate extends Model
{
    protected $primaryKey = 'lease_template_id';

    public const LATE_FEE_TYPES = ['none', 'fixed', 'percent'];

    protected $fillable = [
        'name',
        'welcome_message',
        'body',
        'rent_grace_days',
        'late_fee_type',
        'late_fee_value',
        'notice_days',
        'house_rule_sections',
        'required_documents',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'rent_grace_days' => 'integer',
            'late_fee_value' => 'float',
            'notice_days' => 'integer',
            'house_rule_sections' => 'array',
            'required_documents' => 'array',
        ];
    }

    /** What a tenant would sign. A change to any of these is a new version; renaming is not. */
    public const CONTENT_FIELDS = ['welcome_message', 'body', 'rent_grace_days', 'late_fee_type', 'late_fee_value', 'notice_days', 'house_rule_sections', 'required_documents'];

    /** Save, bumping the version when the content changed. */
    public function saveWithVersion(): bool
    {
        if ($this->exists && $this->isDirty(self::CONTENT_FIELDS)) {
            $this->version = ($this->version ?? 1) + 1;
        }

        return $this->save();
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id', 'user_id');
    }

    public function properties()
    {
        return $this->hasMany(Property::class, 'lease_template_id', 'lease_template_id');
    }

    public function units()
    {
        return $this->hasMany(PropertyUnit::class, 'lease_template_id', 'lease_template_id');
    }

    /** The late fee this template sets: an array, `false` for an explicit "no fee", or null to use the property's rule. */
    public function lateFee(): array|false|null
    {
        return match ($this->late_fee_type) {
            'none' => false,
            'fixed', 'percent' => $this->late_fee_value !== null ? ['type' => $this->late_fee_type, 'value' => (float) $this->late_fee_value] : null,
            default => null,
        };
    }
}
