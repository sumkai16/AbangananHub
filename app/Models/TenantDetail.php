<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

/**
 * An emergency contact and the ID a tenant showed, for the landlord's records. Written through
 * saveFor(); the web sign form, the mobile sign endpoint, Account Settings and the walk-in form all use
 * the same rules() so they cannot disagree about what counts as complete.
 *
 * The ID number is encrypted at rest. It is deliberately never copied into reservations.lease_snapshot,
 * CSV exports or API list payloads: the snapshot is plain JSON and would undo the encryption.
 */
class TenantDetail extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public const ID_TYPES = [
        'PhilSys', "Driver's License", 'Passport', 'UMID', 'Postal ID', 'SSS ID', 'Student ID', 'Other',
    ];

    public const RELATIONSHIPS = ['Parent', 'Sibling', 'Spouse', 'Relative', 'Friend', 'Other'];

    /** The request keys, in form order. */
    public const FIELDS = [
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_number',
        'id_type',
        'id_number',
    ];

    protected $fillable = [...self::FIELDS, 'user_id'];

    /**
     * Same storage format as the `encrypted` cast, but an undecryptable value (APP_KEY changed or rotated)
     * reads as null instead of throwing: signing and several pages read this, and a 500 there is worse than
     * treating the ID as "not on file" so the tenant is simply asked again.
     */
    protected function idNumber(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($value === null) {
                    return null;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException) {
                    return null;
                }
            },
            set: fn (?string $value) => $value === null ? null : Crypt::encryptString($value),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Validation rules for the five fields. `$required` is true where the details must be on file (signing
     * an agreement); the relationship stays optional there, because the name and number are what a
     * landlord actually dials.
     */
    public static function rules(bool $required): array
    {
        $presence = $required ? ['required'] : ['nullable'];

        return [
            'emergency_contact_name'         => [...$presence, 'string', 'max:150'],
            'emergency_contact_relationship' => ['nullable', 'string', Rule::in(self::RELATIONSHIPS)],
            'emergency_contact_number'       => [...$presence, 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'id_type'                        => [...$presence, 'string', Rule::in(self::ID_TYPES)],
            'id_number'                      => [...$presence, 'string', 'max:50'],
        ];
    }

    public static function messages(): array
    {
        return [
            'emergency_contact_number.regex' => 'Enter a valid phone number for your emergency contact.',
            'id_type.in'                     => 'Choose one of the listed ID types.',
        ];
    }

    /**
     * Whether the user has everything a landlord needs on file: a person to call and an ID reference.
     * Reads the loaded relation when there is one so a list page does not query per row.
     */
    public static function completeFor(User $user): bool
    {
        $detail = $user->relationLoaded('tenantDetail') ? $user->tenantDetail : $user->tenantDetail()->first();

        return $detail?->isComplete() ?? false;
    }

    public function isComplete(): bool
    {
        return filled($this->emergency_contact_name)
            && filled($this->emergency_contact_number)
            && filled($this->id_type)
            && filled($this->id_number);
    }

    /**
     * What a tenant still has to give before they can sign, as plain labels. Empty when complete. Lets the
     * sign form and Account Settings say "ID number is missing" instead of showing a wall of red stars.
     *
     * @return list<string>
     */
    public static function missingFor(?self $detail): array
    {
        $required = [
            'emergency_contact_name' => 'emergency contact name',
            'emergency_contact_number' => 'emergency contact number',
            'id_type' => 'ID type',
            'id_number' => 'ID number',
        ];

        return array_values(array_filter($required, fn ($label, $field) => ! filled($detail?->{$field}), ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Writes the validated fields for a user, creating the row on first save. Blank fields are skipped,
     * not saved as null: a walk-in form submitted with the boxes empty (or a client sending only the
     * contact) must never wipe what is already on file. Returns null when there is nothing to store.
     */
    public static function saveFor(User $user, array $validated): ?self
    {
        $values = array_filter(
            array_intersect_key($validated, array_flip(self::FIELDS)),
            fn ($value) => filled($value)
        );

        if ($values === []) {
            return null;
        }

        return static::updateOrCreate(['user_id' => $user->user_id], $values);
    }

    /** The ID number with all but the last four characters hidden, for screens that should not show it in full. */
    public function maskedIdNumber(): ?string
    {
        $value = preg_replace('/\s+/', '', (string) $this->id_number);

        if ($value === '') {
            return null;
        }

        $visible = min(4, strlen($value));

        return str_repeat('•', max(0, strlen($value) - $visible)) . substr($value, -$visible);
    }
}
