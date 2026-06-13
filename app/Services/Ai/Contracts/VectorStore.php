<?php
namespace App\Services\Ai\Contracts;

use App\Models\User;

interface VectorStore
{
    /**
     * Retrieve top-k citation candidates relevant to $query, scoped to the
     * user's tenant + permissions.
     *
     * @return array<int, array{source_type: string, source_id: string, title: string, excerpt: string}>
     */
    public function search(User $user, string $query, int $k = 5): array;
}
