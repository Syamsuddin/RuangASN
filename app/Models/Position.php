<?php
namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use HasUlid, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'name', 'code', 'echelon', 'position_type',
        'grade_level', 'is_head', 'is_active', 'effective_start_date', 'effective_end_date', 'created_by',
    ];

    protected $casts = [
        'is_head'              => 'boolean',
        'is_active'            => 'boolean',
        'effective_start_date' => 'date',
        'effective_end_date'   => 'date',
        'deleted_at'           => 'datetime',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}
