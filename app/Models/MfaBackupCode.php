<?php
namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string $code_hash
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class MfaBackupCode extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['id', 'user_id', 'code_hash', 'used_at'];

    protected $casts = [
        'used_at'    => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
