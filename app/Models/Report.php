<?php

namespace App\Models;

use App\Enums\DataClassification;
use App\Enums\ReportPeriodType;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $pemda_id
 * @property string|null $project_id
 * @property string|null $team_id
 * @property string $title
 * @property string|null $content
 * @property string|null $ai_draft
 * @property ReportType $report_type
 * @property ReportPeriodType $period_type
 * @property ReportStatus $status
 * @property Carbon|null $period_start_date
 * @property Carbon|null $period_end_date
 * @property array|null $data_sources
 * @property DataClassification $data_classification
 * @property string|null $author_id
 * @property Carbon|null $submitted_at
 * @property string|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $published_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Report extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'id', 'organization_id', 'pemda_id', 'project_id', 'team_id',
        'title', 'content', 'ai_draft', 'report_type', 'period_type', 'status',
        'period_start_date', 'period_end_date', 'data_sources', 'data_classification',
        'author_id', 'submitted_at', 'approved_by', 'approved_at', 'published_at',
        'created_by', 'updated_by', 'deleted_by', 'version',
    ];

    protected function casts(): array
    {
        return [
            'status'              => ReportStatus::class,
            'report_type'         => ReportType::class,
            'period_type'         => ReportPeriodType::class,
            'data_classification' => DataClassification::class,
            'data_sources'        => 'array',
            'period_start_date'   => 'date',
            'period_end_date'     => 'date',
            'submitted_at'        => 'datetime',
            'approved_at'         => 'datetime',
            'published_at'        => 'datetime',
            'version'             => 'integer',
            'deleted_at'          => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ReportStatusHistory::class)->orderBy('changed_at');
    }

    public function canTransitionTo(ReportStatus $new, User $user): bool
    {
        $allowed = match ($this->status) {
            ReportStatus::DRAFT     => [ReportStatus::SUBMITTED, ReportStatus::ARCHIVED],
            ReportStatus::SUBMITTED => [ReportStatus::IN_REVIEW, ReportStatus::REVISION, ReportStatus::REJECTED],
            ReportStatus::IN_REVIEW => [ReportStatus::APPROVED, ReportStatus::REVISION, ReportStatus::REJECTED],
            ReportStatus::REVISION  => [ReportStatus::SUBMITTED, ReportStatus::ARCHIVED],
            ReportStatus::APPROVED  => [ReportStatus::PUBLISHED, ReportStatus::ARCHIVED],
            ReportStatus::PUBLISHED => [ReportStatus::ARCHIVED],
            ReportStatus::REJECTED  => [ReportStatus::ARCHIVED],
            ReportStatus::ARCHIVED  => [],
        };

        return in_array($new, $allowed);
    }
}
