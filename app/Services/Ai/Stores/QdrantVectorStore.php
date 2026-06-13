<?php
namespace App\Services\Ai\Stores;

use App\Models\User;
use App\Services\Ai\Contracts\VectorStore;
use App\Services\Ai\RetrievalService;

/**
 * Production vector store stub (config-gated via AI_VECTOR_STORE=qdrant).
 * Qdrant is not provisioned in dev/test, so this currently delegates to the
 * relational RetrievalService. A real Qdrant client wiring is future work
 * (TD-02); the contract + binding seam is in place.
 */
class QdrantVectorStore implements VectorStore
{
    public function __construct(private RetrievalService $retrieval) {}

    /**
     * @return array<int, array{source_type: string, source_id: string, title: string, excerpt: string}>
     */
    public function search(User $user, string $query, int $k = 5): array
    {
        // TODO(Phase 3+): query Qdrant for semantic nearest neighbours, then
        // re-validate each hit through SearchService for permission scoping.
        return $this->retrieval->retrieve($user, $query, $k);
    }
}
