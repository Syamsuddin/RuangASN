<?php

namespace Tests\Feature\Ai;

use App\Enums\DataClassification;
use App\Enums\KnowledgeStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\KnowledgeArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class AiRetrievalTest extends AiTestCase
{
    use RefreshDatabase;

    private function makeArticle(string $title, int $classification, string $status): KnowledgeArticle
    {
        return KnowledgeArticle::create([
            'id'                  => (string) Str::ulid(),
            'organization_id'     => $this->org->id,
            'pemda_id'            => $this->org->id,
            'category_id'         => null,
            'title'               => $title,
            'content'             => '<p>Isi panduan lengkap mengenai prosedur ini.</p>',
            'excerpt'             => 'Ringkasan singkat panduan prosedur.',
            'knowledge_type'      => 'sop',
            'status'              => $status,
            'version_number'      => 1,
            'is_latest'           => true,
            'tags'                => ['sop'],
            'view_count'          => 0,
            'helpful_count'       => 0,
            'data_classification' => $classification,
            'author_id'           => $this->kepalaOpd->id,
            'created_by'          => $this->kepalaOpd->id,
            'published_at'        => $status === KnowledgeStatus::PUBLISHED->value ? now() : null,
        ]);
    }

    // ── 5. RAG citation ─────────────────────────────────────────────────────

    public function test_knowledge_query_cites_visible_article(): void
    {
        $visible = $this->makeArticle(
            'Panduan Pengajuan Cuti Tahunan ASN',
            DataClassification::INTERNAL->value,
            KnowledgeStatus::PUBLISHED->value,
        );

        // A confidential article the asn user CANNOT see (no document.view.confidential).
        $hidden = $this->makeArticle(
            'Panduan Rahasia Pengajuan Cuti Khusus',
            DataClassification::CONFIDENTIAL->value,
            KnowledgeStatus::PUBLISHED->value,
        );

        $this->assertTrue($this->asn->can('view', $visible));
        $this->assertFalse($this->asn->can('view', $hidden));

        $this->actingAs($this->asn)->postJson('/ai/send', [
            'content' => 'bagaimana cara Pengajuan Cuti',
        ])->assertStatus(201);

        $conversation = AiConversation::where('user_id', $this->asn->id)->firstOrFail();
        $assistant = AiMessage::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')->firstOrFail();

        $citations = $assistant->citations ?? [];
        $citedIds  = array_column($citations, 'source_id');

        // Visible article is cited as knowledge.
        $this->assertContains($visible->id, $citedIds);
        $visibleCitation = collect($citations)->firstWhere('source_id', $visible->id);
        $this->assertSame('knowledge', $visibleCitation['source_type']);
        $this->assertSame($visible->title, $visibleCitation['title']);

        // Confidential article is NOT cited (tenant/permission scoped via SearchService).
        $this->assertNotContains($hidden->id, $citedIds);
    }
}
