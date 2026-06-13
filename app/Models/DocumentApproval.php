<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $document_id
 * @property string $approver_id
 * @property int $step_number
 * @property string|null $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class DocumentApproval extends Model
{
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'document_id', 'approver_id', 'step_number',
        'status', 'notes', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at'  => 'datetime',
            'step_number' => 'integer',
        ];
    }

    /** @return BelongsTo<Document, self> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
