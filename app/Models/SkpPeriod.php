<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property int $year
 * @property int|null $semester
 * @property string $name
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SkpPeriod extends Model
{
    use BelongsToOrganization;
    use HasFactory;
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'year', 'semester', 'name',
        'start_date', 'end_date', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'year'       => 'integer',
            'semester'   => 'integer',
            'start_date' => 'date',
            'end_date'   => 'date',
            'is_active'  => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(SkpPlan::class, 'period_id');
    }
}
