<?php
namespace App\Models;

use App\Enums\DelegationType;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $delegator_id
 * @property string $delegate_id
 * @property string $organization_id
 * @property \App\Enums\DelegationType $type
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property bool $is_active
 * @property string|null $sk_number
 * @property string|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Delegation extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'delegator_id', 'delegate_id', 'organization_id', 'type',
        'reason', 'start_date', 'end_date', 'is_active', 'sk_number',
        'approved_by', 'approved_at', 'created_by',
    ];

    protected $casts = [
        'type'        => DelegationType::class,
        'start_date'  => 'date',
        'end_date'    => 'date',
        'is_active'   => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
