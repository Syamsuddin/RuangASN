<?php

namespace App\Http\Resources;

use App\Enums\DataClassification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\KnowledgeArticle */
class KnowledgeArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $classificationLabels = [
            DataClassification::PUBLIC->value       => 'Publik',
            DataClassification::INTERNAL->value     => 'Internal',
            DataClassification::CONFIDENTIAL->value => 'Rahasia',
            DataClassification::RESTRICTED->value   => 'Sangat Rahasia',
        ];

        $typeLabels = [
            'wiki'            => 'Wiki',
            'faq'             => 'FAQ',
            'sop'             => 'SOP',
            'best_practice'   => 'Praktik Terbaik',
            'lesson_learned'  => 'Pelajaran',
            'glossary'        => 'Glosarium',
            'regulation_note' => 'Catatan Regulasi',
            'template'        => 'Template',
            'directory'       => 'Direktori',
        ];

        $level   = $this->data_classification->value;
        $typeVal = $this->knowledge_type->value;

        return [
            'id'                   => $this->id,
            'title'                => $this->title,
            'content'              => $this->content,
            'excerpt'              => $this->excerpt,
            'knowledge_type'       => $typeVal,
            'type_label'           => $typeLabels[$typeVal],
            'status'               => $this->status->value,
            'version_number'       => $this->version_number,
            'parent_article_id'    => $this->parent_article_id,
            'is_latest'            => $this->is_latest,
            'ai_summary'           => $this->ai_summary,
            'tags'                 => $this->tags ?? [],
            'view_count'           => $this->view_count,
            'helpful_count'        => $this->helpful_count,
            'data_classification'  => $level,
            'classification_label' => $classificationLabels[$level],
            'organization_id'      => $this->organization_id,
            'author_id'            => $this->author_id,
            'published_at'         => $this->published_at?->toISOString(),
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),

            'author' => $this->whenLoaded('author', fn () => $this->author ? [
                'id' => $this->author->id, 'name' => $this->author->name,
            ] : null),

            'publisher' => $this->whenLoaded('publisher', fn () => $this->publisher ? [
                'id' => $this->publisher->id, 'name' => $this->publisher->name,
            ] : null),

            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug,
            ] : null),

            'versions' => $this->whenLoaded('versions', fn () => $this->versions->map(fn ($v) => [
                'id'             => $v->id,
                'version_number' => $v->version_number,
                'status'         => $v->status->value,
                'is_latest'      => $v->is_latest,
                'created_at'     => $v->created_at?->toISOString(),
            ])),
        ];
    }
}
