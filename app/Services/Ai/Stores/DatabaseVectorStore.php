<?php
namespace App\Services\Ai\Stores;

use App\Models\User;
use App\Services\Ai\Contracts\VectorStore;
use App\Services\Ai\RetrievalService;

/**
 * Working dev/test vector store: thin adapter over RetrievalService (which is
 * tenant + permission scoped via SearchService). No external dependency.
 */
class DatabaseVectorStore implements VectorStore
{
    public function __construct(private RetrievalService $retrieval) {}

    /**
     * @return array<int, array{source_type: string, source_id: string, title: string, excerpt: string}>
     */
    public function search(User $user, string $query, int $k = 5): array
    {
        return $this->retrieval->retrieve($user, $query, $k);
    }
}
