<?php
namespace App\Services\Ai;

use App\Models\User;
use App\Services\Ai\Contracts\EmbeddingProvider;
use App\Services\SearchService;
use Illuminate\Support\Str;

/**
 * RAG retrieval: pulls candidate items from SearchService (already tenant +
 * permission scoped across knowledge/documents/meetings/reports/tasks),
 * builds citation rows, then optionally re-ranks by fake-embedding cosine
 * similarity. Returns the top-k citations attached to an ai_message.
 *
 * AXIOM-08: candidates come ONLY from SearchService, so the AI never sees
 * anything the active user couldn't see themselves.
 */
class RetrievalService
{
    public function __construct(
        private SearchService $search,
        private EmbeddingProvider $embeddings,
    ) {}

    /**
     * @return array<int, array{source_type: string, source_id: string, title: string, excerpt: string}>
     */
    public function retrieve(User $user, string $query, int $k = 5): array
    {
        $excerptLen = (int) config('ai.retrieval.excerpt_length', 160);

        // Prioritise knowledge + documents + meetings + reports for RAG context.
        $types = ['knowledge', 'document', 'meeting', 'report'];

        // SearchService matches the whole query as one term (substring on
        // SQLite / plainto_tsquery on PostgreSQL). To approximate term-based
        // retrieval consistently, query the full phrase AND each significant
        // token, then merge + dedupe by source_id. All candidates still come
        // ONLY from SearchService → tenant + permission scoped (AXIOM-08).
        $queries = array_merge([$query], $this->significantTerms($query));

        $citations = [];
        $seen      = [];
        foreach ($queries as $q) {
            if ($q === '') {
                continue;
            }
            $results = $this->search->search($user, $q, $types, max($k, 5));
            foreach ($results as $type => $items) {
                foreach ($items as $item) {
                    $id = (string) ($item['id'] ?? '');
                    if ($id === '' || isset($seen[$id])) {
                        continue;
                    }
                    $seen[$id] = true;

                    $citations[] = [
                        'source_type' => $type,
                        'source_id'   => $id,
                        'title'       => (string) ($item['title'] ?? ''),
                        'excerpt'     => Str::limit(strip_tags((string) ($item['snippet'] ?? '')), $excerptLen, '...'),
                    ];
                }
            }
        }

        if ($citations === []) {
            return [];
        }

        return $this->rankByEmbedding($query, $citations, $k);
    }

    /**
     * Extract significant tokens (drops short words + common Indonesian
     * question/stop words) so retrieval works on substring-only drivers.
     *
     * @return array<int, string>
     */
    private function significantTerms(string $query): array
    {
        $stop = [
            'apa', 'itu', 'cara', 'bagaimana', 'yang', 'untuk', 'dari', 'dan',
            'atau', 'dengan', 'pada', 'ke', 'di', 'adalah', 'tentang', 'mengenai',
            'jelaskan', 'cari', 'carikan', 'tolong', 'mohon', 'saya', 'kami',
        ];

        $tokens = preg_split('/\s+/', trim($query)) ?: [];
        $terms  = [];
        foreach ($tokens as $token) {
            $clean = mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}]/u', '', $token)));
            if (mb_strlen($clean) < 4 || in_array($clean, $stop, true)) {
                continue;
            }
            $terms[$clean] = $token; // preserve original casing for matching
        }

        return array_values($terms);
    }

    /**
     * @param array<int, array{source_type: string, source_id: string, title: string, excerpt: string}> $citations
     * @return array<int, array{source_type: string, source_id: string, title: string, excerpt: string}>
     */
    private function rankByEmbedding(string $query, array $citations, int $k): array
    {
        $queryVec = $this->embeddings->embed($query);

        $scored = [];
        foreach ($citations as $i => $c) {
            $candidateVec = $this->embeddings->embed($c['title'] . ' ' . $c['excerpt']);
            $scored[] = [
                'index'      => $i,
                'similarity' => $this->cosine($queryVec, $candidateVec),
                'citation'   => $c,
            ];
        }

        // Stable sort: higher similarity first, ties keep original order.
        usort($scored, static function ($a, $b) {
            if ($a['similarity'] === $b['similarity']) {
                return $a['index'] <=> $b['index'];
            }
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_map(
            static fn ($s) => $s['citation'],
            array_slice($scored, 0, $k)
        );
    }

    /**
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    private function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $na  = 0.0;
        $nb  = 0.0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $na  += $a[$i] * $a[$i];
            $nb  += $b[$i] * $b[$i];
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
