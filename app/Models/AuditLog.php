<?php
namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $organization_id
 * @property string|null $user_id
 * @property string $action
 * @property string|null $auditable_type
 * @property string|null $auditable_id
 * @property array|null $old_values
 * @property array|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $url
 * @property string|null $hash
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class AuditLog extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id', 'organization_id', 'user_id', 'action',
        'auditable_type', 'auditable_id', 'old_values', 'new_values',
        'ip_address', 'user_agent', 'url', 'hash',
    ];

    protected $casts = [
        'old_values'  => 'array',
        'new_values'  => 'array',
        'created_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
