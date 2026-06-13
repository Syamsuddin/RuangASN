<?php

namespace App\Models;

use App\Enums\DataClassification;
use App\Enums\KnowledgeStatus;
use App\Enums\KnowledgeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $pemda_id
 * @property string|null $category_id
 * @property string $title
 * @property string|null $content
 * @property string|null $excerpt
 * @property KnowledgeType $knowledge_type
 * @property KnowledgeStatus $status
 * @property int $version_number
 * @property string|null $parent_article_id
 * @property bool $is_latest
 * @property string|null $embedding_id
 * @property string|null $embedding_model
 * @property Carbon|null $embedded_at
 * @property string|null $ai_summary
 * @property array|null $tags
 * @property int $view_count
 * @property int $helpful_count
 * @property DataClassification $data_classification
 * @property string $author_id
 * @property string|null $published_by
 * @property Carbon|null $published_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class KnowledgeArticle extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'id', 'organization_id', 'pemda_id', 'category_id',
        'title', 'content', 'excerpt',
        'knowledge_type', 'status', 'version_number',
        'parent_article_id', 'is_latest',
        'embedding_id', 'embedding_model', 'embedded_at',
        'ai_summary', 'tags', 'view_count', 'helpful_count',
        'data_classification', 'author_id',
        'published_by', 'published_at',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'status'              => KnowledgeStatus::class,
            'knowledge_type'      => KnowledgeType::class,
            'data_classification' => DataClassification::class,
            'tags'                => 'array',
            'is_latest'           => 'boolean',
            'view_count'          => 'integer',
            'helpful_count'       => 'integer',
            'version_number'      => 'integer',
            'embedded_at'         => 'datetime',
            'published_at'        => 'datetime',
            'deleted_at'          => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'parent_article_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeArticle::class, 'parent_article_id');
    }

    public function canTransitionTo(KnowledgeStatus $new, User $user): bool
    {
        $allowed = match ($this->status) {
            KnowledgeStatus::DRAFT     => [KnowledgeStatus::IN_REVIEW, KnowledgeStatus::ARCHIVED],
            KnowledgeStatus::IN_REVIEW => [KnowledgeStatus::PUBLISHED, KnowledgeStatus::DRAFT, KnowledgeStatus::ARCHIVED],
            KnowledgeStatus::PUBLISHED => [KnowledgeStatus::OUTDATED, KnowledgeStatus::ARCHIVED],
            KnowledgeStatus::OUTDATED  => [KnowledgeStatus::IN_REVIEW, KnowledgeStatus::ARCHIVED],
            KnowledgeStatus::ARCHIVED  => [],
        };

        return in_array($new, $allowed);
    }

    public function incrementViews(): void
    {
        $this->increment('view_count');
    }
}
