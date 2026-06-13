<?php

namespace App\Models;

use App\Enums\DataClassification;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string|null $pemda_id
 * @property string|null $meeting_id
 * @property string|null $project_id
 * @property string|null $task_id
 * @property string $title
 * @property string|null $description
 * @property DocumentType $document_type
 * @property DocumentStatus $status
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int|null $file_size
 * @property string|null $mime_type
 * @property int|null $page_count
 * @property int $version_number
 * @property string|null $parent_document_id
 * @property bool $is_latest
 * @property string|null $ocr_text
 * @property string|null $ai_summary
 * @property array|null $ai_tags
 * @property string|null $ai_category
 * @property string|null $document_number
 * @property Carbon|null $document_date
 * @property Carbon|null $effective_date
 * @property Carbon|null $expiry_date
 * @property array|null $tags
 * @property DataClassification $data_classification
 * @property string|null $owner_id
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Document extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'id', 'organization_id', 'pemda_id', 'meeting_id', 'project_id', 'task_id',
        'title', 'description', 'document_type', 'status',
        'file_path', 'file_name', 'file_size', 'mime_type', 'page_count',
        'version_number', 'parent_document_id', 'is_latest',
        'ocr_text', 'ai_summary', 'ai_tags', 'ai_category',
        'document_number', 'document_date', 'effective_date', 'expiry_date',
        'tags', 'data_classification', 'owner_id', 'created_by', 'updated_by', 'deleted_by', 'version',
    ];

    protected function casts(): array
    {
        return [
            'status'              => DocumentStatus::class,
            'document_type'       => DocumentType::class,
            'data_classification' => DataClassification::class,
            'ai_tags'             => 'array',
            'tags'                => 'array',
            'is_latest'           => 'boolean',
            'document_date'       => 'date',
            'effective_date'      => 'date',
            'expiry_date'         => 'date',
            'version'             => 'integer',
            'version_number'      => 'integer',
            'page_count'          => 'integer',
            'deleted_at'          => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function parent(): BelongsTo { return $this->belongsTo(Document::class, 'parent_document_id'); }
    public function versions(): HasMany { return $this->hasMany(Document::class, 'parent_document_id'); }
    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function task(): BelongsTo { return $this->belongsTo(Task::class); }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class)->orderBy('step_number');
    }

    public function canTransitionTo(DocumentStatus $new, User $user): bool
    {
        $allowed = match ($this->status) {
            DocumentStatus::DRAFT      => [DocumentStatus::IN_REVIEW, DocumentStatus::ARCHIVED],
            DocumentStatus::IN_REVIEW  => [DocumentStatus::APPROVED, DocumentStatus::REJECTED],
            DocumentStatus::APPROVED   => [DocumentStatus::PUBLISHED, DocumentStatus::ARCHIVED],
            DocumentStatus::PUBLISHED  => [DocumentStatus::SUPERSEDED, DocumentStatus::EXPIRED, DocumentStatus::ARCHIVED],
            DocumentStatus::REJECTED   => [DocumentStatus::IN_REVIEW, DocumentStatus::ARCHIVED],
            DocumentStatus::EXPIRED    => [DocumentStatus::ARCHIVED],
            DocumentStatus::SUPERSEDED => [DocumentStatus::ARCHIVED],
            DocumentStatus::ARCHIVED   => [],
        };
        return in_array($new, $allowed);
    }

    public function latestApprovalStep(): ?DocumentApproval
    {
        /** @var DocumentApproval|null */
        return $this->approvals()->orderByDesc('step_number')->first();
    }
}
