<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $indicator_id
 * @property string $user_id
 * @property float $realization_value
 * @property Carbon $realization_date
 * @property string|null $description
 * @property string|null $task_id
 * @property string|null $document_id
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SkpRealization extends Model
{
    use HasFactory;
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'indicator_id', 'user_id', 'realization_value',
        'realization_date', 'description', 'task_id', 'document_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'realization_value' => 'decimal:4',
            'realization_date'  => 'date',
        ];
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(SkpIndicator::class, 'indicator_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
