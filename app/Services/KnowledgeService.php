<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\KnowledgeStatus;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KnowledgeService
{
    public function __construct(
        private OutboxPublisher $outbox,
        private AuditService $audit,
    ) {}

    public function create(array $data, User $author): KnowledgeArticle
    {
        return DB::transaction(function () use ($data, $author) {
            $data['excerpt'] = $data['excerpt'] ?? $this->buildExcerpt($data['content'] ?? '');

            $article = KnowledgeArticle::create([
                ...$data,
                'organization_id' => $author->organization_id,
                'pemda_id'        => $author->pemda_id,
                'author_id'       => $author->id,
                'created_by'      => $author->id,
                'status'          => KnowledgeStatus::DRAFT->value,
                'version_number'  => 1,
                'is_latest'       => true,
            ]);

            $article->refresh();
            $this->outbox->publish('knowledge.created', $article->toArray(), 'KnowledgeArticle', $article->id);

            return $article;
        });
    }

    public function update(KnowledgeArticle $article, array $data): KnowledgeArticle
    {
        return DB::transaction(function () use ($article, $data) {
            if (isset($data['content']) && empty($data['excerpt'])) {
                $data['excerpt'] = $this->buildExcerpt($data['content']);
            }

            $article->update($data);
            $article->refresh();
            $this->outbox->publish('knowledge.updated', $article->toArray(), 'KnowledgeArticle', $article->id);

            return $article;
        });
    }

    public function transition(KnowledgeArticle $article, KnowledgeStatus $new, User $actor): KnowledgeArticle
    {
        return DB::transaction(function () use ($article, $new, $actor) {
            if (! $article->canTransitionTo($new, $actor)) {
                throw ValidationException::withMessages([
                    'status' => "Tidak dapat berpindah dari status {$article->status->value} ke {$new->value}.",
                ]);
            }

            $old = $article->status->value;
            $update = ['status' => $new->value];

            if ($new === KnowledgeStatus::PUBLISHED) {
                $update['published_by'] = $actor->id;
                $update['published_at'] = now();
            }

            $article->update($update);
            $article->refresh();

            $this->outbox->publish('knowledge.status_changed', [
                'article_id'      => $article->id,
                'organization_id' => $article->organization_id,
                'from_status'     => $old,
                'to_status'       => $new->value,
                'changed_by'      => $actor->id,
            ], 'KnowledgeArticle', $article->id);

            $this->audit->log(AuditAction::STATUS_CHANGED, 'KnowledgeArticle', $article->id,
                ['status' => $old], ['status' => $new->value]
            );

            return $article;
        });
    }

    public function createNewVersion(KnowledgeArticle $article, array $data, User $actor): KnowledgeArticle
    {
        return DB::transaction(function () use ($article, $data, $actor) {
            $allowed = [KnowledgeStatus::PUBLISHED, KnowledgeStatus::OUTDATED];
            if (! in_array($article->status, $allowed)) {
                throw ValidationException::withMessages([
                    'status' => 'Versi baru hanya dapat dibuat dari artikel berstatus published atau outdated.',
                ]);
            }

            $data['excerpt'] = $data['excerpt'] ?? $this->buildExcerpt($data['content'] ?? $article->content ?? '');

            $newArticle = KnowledgeArticle::create([
                ...$data,
                'organization_id'   => $article->organization_id,
                'pemda_id'          => $article->pemda_id,
                'author_id'         => $actor->id,
                'created_by'        => $actor->id,
                'parent_article_id' => $article->id,
                'version_number'    => $article->version_number + 1,
                'is_latest'         => true,
                'status'            => KnowledgeStatus::DRAFT->value,
            ]);

            $article->update(['is_latest' => false]);

            if ($article->status === KnowledgeStatus::PUBLISHED) {
                $article->update(['status' => KnowledgeStatus::OUTDATED->value]);
            }

            $this->outbox->publish('knowledge.versioned', [
                'article_id'        => $newArticle->id,
                'parent_article_id' => $article->id,
                'organization_id'   => $article->organization_id,
                'version_number'    => $newArticle->version_number,
            ], 'KnowledgeArticle', $newArticle->id);

            return $newArticle;
        });
    }

    public function recordView(KnowledgeArticle $article): void
    {
        $article->incrementViews();
    }

    public function markHelpful(KnowledgeArticle $article): void
    {
        $article->increment('helpful_count');
    }

    public function createCategory(array $data, User $actor): KnowledgeCategory
    {
        return DB::transaction(function () use ($data, $actor) {
            $data['organization_id'] = $actor->organization_id;
            $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

            return KnowledgeCategory::create($data);
        });
    }

    public function updateCategory(KnowledgeCategory $category, array $data): KnowledgeCategory
    {
        return DB::transaction(function () use ($category, $data) {
            if (isset($data['name']) && ! isset($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $category->update($data);
            $category->refresh();

            return $category;
        });
    }

    private function buildExcerpt(string $content): string
    {
        return Str::limit(strip_tags($content), 200);
    }
}
