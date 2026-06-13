<?php
namespace App\Models;

use App\Enums\OrganizationType;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $parent_id
 * @property \App\Enums\OrganizationType $type
 * @property string $name
 * @property string|null $short_name
 * @property string|null $code
 * @property string|null $description
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $logo_path
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $effective_start_date
 * @property \Illuminate\Support\Carbon|null $effective_end_date
 * @property int|null $lft
 * @property int|null $rgt
 * @property int $depth
 * @property string|null $pemda_id
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Organization extends Model
{
    use HasFactory;
    use HasUlid;
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'parent_id', 'type', 'name', 'short_name', 'code',
        'description', 'address', 'phone', 'email', 'logo_path',
        'is_active', 'effective_start_date', 'effective_end_date',
        'lft', 'rgt', 'depth', 'pemda_id',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active'            => 'boolean',
            'effective_start_date' => 'date',
            'effective_end_date'   => 'date',
            'depth'                => 'integer',
            'lft'                  => 'integer',
            'rgt'                  => 'integer',
            'type'                 => OrganizationType::class,
            'deleted_at'           => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Organization::class, 'parent_id');
    }

    public function pemda(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'pemda_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'organization_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function isPemda(): bool
    {
        return $this->type === OrganizationType::GOVERNMENT;
    }
}
