<?php
namespace App\Models;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $pemda_id
 * @property string $organization_id
 * @property string|null $type
 * @property string $name
 * @property string|null $description
 * @property bool $is_cross_opd
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property string|null $sk_number
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Team extends Model
{
    use BelongsToOrganization, HasUlid, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'pemda_id', 'organization_id', 'type', 'name', 'description',
        'is_cross_opd', 'is_active', 'start_date', 'end_date', 'sk_number',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_cross_opd' => 'boolean',
        'is_active'    => 'boolean',
        'start_date'   => 'date',
        'end_date'     => 'date',
        'deleted_at'   => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function pemda(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'pemda_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('role', 'joined_at', 'left_at', 'is_active')
            ->wherePivot('is_active', true);
    }
}
