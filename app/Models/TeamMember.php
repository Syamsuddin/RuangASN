<?php
namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $team_id
 * @property string $user_id
 * @property string|null $role
 * @property \Illuminate\Support\Carbon|null $joined_at
 * @property \Illuminate\Support\Carbon|null $left_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class TeamMember extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id', 'team_id', 'user_id', 'role', 'joined_at', 'left_at', 'is_active',
    ];

    protected $casts = [
        'joined_at'  => 'date',
        'left_at'    => 'date',
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
