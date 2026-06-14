<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Daily aggregated KPI snapshot for one organization (Phase 4, Sprint 19-20).
 *
 * One row per (organization_id, snapshot_date, scope). `metrics` is the
 * denormalized KPI bundle produced by AnalyticsService::computeSnapshot()
 * (tasks/meetings/documents/reports/projects/skp/users/notifications). Rows are
 * an upsert ledger keyed by the unique constraint — they carry no soft-delete
 * (analytics, not domain state). The org global scope still applies via
 * BelongsToOrganization so a snapshot never leaks across tenants.
 *
 * @property string $id
 * @property string $organization_id
 * @property Carbon $snapshot_date
 * @property string $scope
 * @property array<string, mixed> $metrics
 * @property Carbon|null $created_at
 * @property-read Organization|null $organization
 *
 * @phpstan-consistent-constructor
 */
class AnalyticsSnapshot extends Model
{
    use BelongsToOrganization;
    use HasUlid;

    public $incrementing = false;
    protected $keyType = 'string';

    /** Append/upsert ledger: created_at only, no updated_at. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'id', 'organization_id', 'snapshot_date', 'scope', 'metrics',
    ];

    protected function casts(): array
    {
        return [
            'metrics'       => 'array',
            // Store/serialize as a plain Y-m-d so the unique key and the
            // updateOrCreate match value (a date string) compare identically —
            // a 'date' cast would append 00:00:00 and break idempotency lookups.
            'snapshot_date' => 'date:Y-m-d',
            'created_at'    => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
