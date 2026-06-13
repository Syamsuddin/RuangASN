<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $report_id
 * @property string|null $from_status
 * @property string|null $to_status
 * @property string|null $changed_by
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $changed_at
 */
class ReportStatusHistory extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = [
        'id', 'report_id', 'from_status', 'to_status',
        'changed_by', 'notes', 'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
