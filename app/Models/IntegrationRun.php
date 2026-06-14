<?php
namespace App\Models;

use App\Enums\IntegrationRunStatus;
use App\Traits\BelongsToOrganization;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One observability record per sync attempt or inbound webhook delivery.
 * Append-only: created with status RUNNING, then finalized in place. The
 * payload_excerpt is a REDACTED preview only — never raw secrets / full PII.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $provider
 * @property string $direction
 * @property string $operation
 * @property IntegrationRunStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int $items_processed
 * @property int $items_failed
 * @property string|null $summary
 * @property string|null $error_message
 * @property string|null $payload_excerpt
 * @property string|null $triggered_by
 * @property Carbon|null $created_at
 * @property-read Organization $organization
 * @property-read User|null $triggeredBy
 *
 * @phpstan-consistent-constructor
 */
class IntegrationRun extends Model
{
    use BelongsToOrganization;
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    /** No updated_at: rows are finalized in place during the same request. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'id', 'organization_id', 'provider', 'direction', 'operation', 'status',
        'started_at', 'finished_at', 'items_processed', 'items_failed',
        'summary', 'error_message', 'payload_excerpt', 'triggered_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status'          => IntegrationRunStatus::class,
            'started_at'      => 'datetime',
            'finished_at'     => 'datetime',
            'items_processed' => 'integer',
            'items_failed'    => 'integer',
            'created_at'      => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (IntegrationRun $run) {
            if (empty($run->created_at)) {
                $run->created_at = now();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
