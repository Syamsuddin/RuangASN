<?php
namespace App\Services\Ai\Agents;

use App\Enums\AiAgentType;

/**
 * Knowledge Agent: RAG Q&A. Citations are injected by the orchestrator via
 * RetrievalService (already tenant + permission scoped, AXIOM-08), so this
 * agent only frames the role — the retrieved context drives the answer.
 */
class KnowledgeAgent extends BaseAgent
{
    public function type(): AiAgentType
    {
        return AiAgentType::KNOWLEDGE;
    }

    protected function role(): string
    {
        return 'Anda adalah AI Knowledge Agent RuangASN yang menjawab pertanyaan HANYA berdasarkan '
            . 'konteks basis pengetahuan organisasi yang diberikan, dan SELALU menyertakan rujukan sumber.';
    }
}
