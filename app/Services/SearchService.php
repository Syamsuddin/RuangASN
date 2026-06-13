<?php

namespace App\Services;

use App\Models\Document;
use App\Models\KnowledgeArticle;
use App\Models\Meeting;
use App\Models\Report;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SearchService
{
    private const SNIPPET_LENGTH = 160;
    private const OVERSCAN_MULTIPLIER = 3;

    /** @return array<string, array<int, array<string, mixed>>> */
    public function search(User $user, string $query, array $types = [], int $perType = 5): array
    {
        $allTypes = ['task', 'meeting', 'document', 'report', 'knowledge'];
        $active   = empty($types) ? $allTypes : array_intersect($types, $allTypes);

        $results = [];
        foreach ($active as $type) {
            $results[$type] = $this->searchType($user, $type, $query, $perType);
        }

        return $results;
    }

    /** @return array<int, array<string, mixed>> */
    public function suggest(User $user, string $query, int $limit = 8): array
    {
        $results = $this->search($user, $query, [], (int) ceil($limit / 5));

        $flat = [];
        foreach ($results as $items) {
            foreach ($items as $item) {
                $flat[] = $item;
                if (count($flat) >= $limit) {
                    break 2;
                }
            }
        }

        return $flat;
    }

    /** @return array<int, array<string, mixed>> */
    private function searchType(User $user, string $type, string $query, int $perType): array
    {
        $candidates = match ($type) {
            'task'      => $this->searchTasks($user, $query, $perType),
            'meeting'   => $this->searchMeetings($user, $query, $perType),
            'document'  => $this->searchDocuments($user, $query, $perType),
            'report'    => $this->searchReports($user, $query, $perType),
            'knowledge' => $this->searchKnowledge($user, $query, $perType),
            default     => collect(),
        };

        return $candidates
            ->filter(fn($item) => $user->can('view', $item))
            ->take($perType)
            ->values()
            ->map(fn($item) => $this->normalize($type, $item))
            ->all();
    }

    /** @return Collection<int, Task> */
    private function searchTasks(User $user, string $query, int $perType): Collection
    {
        /** @var Collection<int, Task> */
        return Task::query()
            ->when(
                $this->isPgsql(),
                fn(Builder $q) => $q->whereRaw(
                    "to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(description,'')) @@ plainto_tsquery('simple', ?)",
                    [$query]
                ),
                fn(Builder $q) => $q->where(function (Builder $inner) use ($query) {
                    $inner->where('title', 'like', "%{$query}%")
                          ->orWhere('description', 'like', "%{$query}%");
                })
            )
            ->limit($perType * self::OVERSCAN_MULTIPLIER)
            ->get();
    }

    /** @return Collection<int, Meeting> */
    private function searchMeetings(User $user, string $query, int $perType): Collection
    {
        /** @var Collection<int, Meeting> */
        return Meeting::query()
            ->when(
                $this->isPgsql(),
                fn(Builder $q) => $q->whereRaw(
                    "to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(description,'')) @@ plainto_tsquery('simple', ?)",
                    [$query]
                ),
                fn(Builder $q) => $q->where(function (Builder $inner) use ($query) {
                    $inner->where('title', 'like', "%{$query}%")
                          ->orWhere('description', 'like', "%{$query}%");
                })
            )
            ->limit($perType * self::OVERSCAN_MULTIPLIER)
            ->get();
    }

    /** @return Collection<int, Document> */
    private function searchDocuments(User $user, string $query, int $perType): Collection
    {
        /** @var Collection<int, Document> */
        return Document::query()
            ->when(
                $this->isPgsql(),
                fn(Builder $q) => $q->whereRaw(
                    "to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(description,'')) @@ plainto_tsquery('simple', ?)",
                    [$query]
                ),
                fn(Builder $q) => $q->where(function (Builder $inner) use ($query) {
                    $inner->where('title', 'like', "%{$query}%")
                          ->orWhere('description', 'like', "%{$query}%");
                })
            )
            ->limit($perType * self::OVERSCAN_MULTIPLIER)
            ->get();
    }

    /** @return Collection<int, Report> */
    private function searchReports(User $user, string $query, int $perType): Collection
    {
        /** @var Collection<int, Report> */
        return Report::query()
            ->when(
                $this->isPgsql(),
                fn(Builder $q) => $q->whereRaw(
                    "to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(content,'')) @@ plainto_tsquery('simple', ?)",
                    [$query]
                ),
                fn(Builder $q) => $q->where(function (Builder $inner) use ($query) {
                    $inner->where('title', 'like', "%{$query}%")
                          ->orWhere('content', 'like', "%{$query}%");
                })
            )
            ->limit($perType * self::OVERSCAN_MULTIPLIER)
            ->get();
    }

    /** @return Collection<int, KnowledgeArticle> */
    private function searchKnowledge(User $user, string $query, int $perType): Collection
    {
        /** @var Collection<int, KnowledgeArticle> */
        return KnowledgeArticle::query()
            ->when(
                $this->isPgsql(),
                fn(Builder $q) => $q->whereRaw(
                    "to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(excerpt,'') || ' ' || coalesce(content,'')) @@ plainto_tsquery('simple', ?)",
                    [$query]
                ),
                fn(Builder $q) => $q->where(function (Builder $inner) use ($query) {
                    $inner->where('title', 'like', "%{$query}%")
                          ->orWhere('excerpt', 'like', "%{$query}%")
                          ->orWhere('content', 'like', "%{$query}%");
                })
            )
            ->limit($perType * self::OVERSCAN_MULTIPLIER)
            ->get();
    }

    private function isPgsql(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /** @param Task|Meeting|Document|Report|KnowledgeArticle $item */
    private function normalize(string $type, mixed $item): array
    {
        [$title, $body, $meta, $url] = match ($type) {
            'task'      => $this->normTask($item),
            'meeting'   => $this->normMeeting($item),
            'document'  => $this->normDocument($item),
            'report'    => $this->normReport($item),
            'knowledge' => $this->normKnowledge($item),
            default     => [$item->title ?? '', '', [], '#'],
        };

        return [
            'type'    => $type,
            'id'      => $item->id,
            'title'   => $title,
            'snippet' => $this->snippet($body),
            'meta'    => $meta,
            'url'     => $url,
        ];
    }

    /** @param Task $item */
    private function normTask(mixed $item): array
    {
        return [
            $item->title,
            (string) ($item->description ?? ''),
            ['status' => $item->status->value, 'priority' => $item->priority->value],
            "/tasks/{$item->id}",
        ];
    }

    /** @param Meeting $item */
    private function normMeeting(mixed $item): array
    {
        return [
            $item->title,
            (string) ($item->description ?? ''),
            ['status' => $item->status->value, 'scheduled_at' => $item->scheduled_at?->toDateString()],
            "/meetings/{$item->id}",
        ];
    }

    /** @param Document $item */
    private function normDocument(mixed $item): array
    {
        return [
            $item->title,
            (string) ($item->description ?? ''),
            ['status' => $item->status->value, 'type' => $item->document_type->value],
            "/documents/{$item->id}",
        ];
    }

    /** @param Report $item */
    private function normReport(mixed $item): array
    {
        return [
            $item->title,
            strip_tags((string) ($item->content ?? '')),
            ['status' => $item->status->value],
            "/reports/{$item->id}",
        ];
    }

    /** @param KnowledgeArticle $item */
    private function normKnowledge(mixed $item): array
    {
        $body = $item->excerpt ?? strip_tags((string) ($item->content ?? ''));
        return [
            $item->title,
            $body,
            ['status' => $item->status->value, 'type' => $item->knowledge_type->value],
            "/knowledge/{$item->id}",
        ];
    }

    private function snippet(string $text): string
    {
        $clean = strip_tags($text);
        return Str::limit($clean, self::SNIPPET_LENGTH, '...');
    }
}
