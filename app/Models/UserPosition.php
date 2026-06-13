<?php
namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string $position_id
 * @property string $organization_id
 * @property string|null $direct_superior_id
 * @property \Illuminate\Support\Carbon|null $effective_start_date
 * @property \Illuminate\Support\Carbon|null $effective_end_date
 * @property bool $is_current
 * @property string|null $sk_number
 * @property \Illuminate\Support\Carbon|null $sk_date
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class UserPosition extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'position_id', 'organization_id', 'direct_superior_id',
        'effective_start_date', 'effective_end_date', 'is_current', 'sk_number', 'sk_date', 'created_by',
    ];

    protected $casts = [
        'is_current'           => 'boolean',
        'effective_start_date' => 'date',
        'effective_end_date'   => 'date',
        'sk_date'              => 'date',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function position(): BelongsTo { return $this->belongsTo(Position::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function directSuperior(): BelongsTo { return $this->belongsTo(User::class, 'direct_superior_id'); }
}
