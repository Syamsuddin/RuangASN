<?php

namespace App\Policies;

use App\Enums\DataClassification;
use App\Enums\KnowledgeStatus;
use App\Models\KnowledgeArticle;
use App\Models\User;

class KnowledgeArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('knowledge.view.internal');
    }

    public function view(User $user, KnowledgeArticle $article): bool
    {
        $status = $article->status;

        // Author can always view their own
        if ($article->author_id === $user->id) {
            return true;
        }

        // Draft/in_review only author or publisher
        if (in_array($status, [KnowledgeStatus::DRAFT, KnowledgeStatus::IN_REVIEW])) {
            return $user->hasPermissionTo('knowledge.publish');
        }

        // Published/outdated: check classification
        if (! $user->hasPermissionTo('knowledge.view.internal')) {
            return false;
        }

        $level = $article->data_classification;

        if ($level === DataClassification::RESTRICTED) {
            return $user->hasPermissionTo('document.view.restricted');
        }

        if ($level === DataClassification::CONFIDENTIAL) {
            return $user->hasPermissionTo('document.view.confidential');
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('knowledge.create');
    }

    public function update(User $user, KnowledgeArticle $article): bool
    {
        $editableStatuses = [
            KnowledgeStatus::DRAFT->value,
            KnowledgeStatus::IN_REVIEW->value,
            KnowledgeStatus::OUTDATED->value,
        ];

        if (! in_array($article->status->value, $editableStatuses)) {
            return false;
        }

        if ($user->hasPermissionTo('knowledge.edit.own') && $article->author_id === $user->id) {
            return true;
        }

        return $user->hasPermissionTo('knowledge.publish');
    }

    public function publish(User $user, KnowledgeArticle $article): bool
    {
        return $user->hasPermissionTo('knowledge.publish')
            && $article->status === KnowledgeStatus::IN_REVIEW;
    }

    public function archive(User $user, KnowledgeArticle $article): bool
    {
        return $user->hasPermissionTo('knowledge.archive')
            && $article->status !== KnowledgeStatus::ARCHIVED;
    }

    public function delete(User $user, KnowledgeArticle $article): bool
    {
        return $article->author_id === $user->id
            && $article->status === KnowledgeStatus::DRAFT;
    }

    public function createVersion(User $user, KnowledgeArticle $article): bool
    {
        return $user->hasPermissionTo('knowledge.create')
            && in_array($article->status, [KnowledgeStatus::PUBLISHED, KnowledgeStatus::OUTDATED]);
    }
}
