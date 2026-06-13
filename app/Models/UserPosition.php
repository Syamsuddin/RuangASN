<?php
namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
