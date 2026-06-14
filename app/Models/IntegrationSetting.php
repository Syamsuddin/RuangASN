<?php
namespace App\Models;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single per-organization external integration setting.
 *
 * Non-secret values are stored plain in `value` (queryable). Secret values are
 * stored ENCRYPTED (Crypt::encryptString) — encryption/decryption is handled by
 * IntegrationSettingsService based on `is_secret`, NOT by an Eloquent cast, so
 * non-secret rows stay plain.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property bool $is_secret
 * @property string|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read User|null $updatedBy
 *
 * @phpstan-consistent-constructor
 */
class IntegrationSetting extends Model
{
    use BelongsToOrganization;
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'group', 'key', 'value', 'is_secret', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_secret'  => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
