<?php
namespace App\Services\Ai\Agents;

use App\Enums\AiAgentType;

/**
 * General fallback chat agent. No specialised context or actions.
 */
class GeneralAgent extends BaseAgent
{
    public function type(): AiAgentType
    {
        return AiAgentType::GENERAL;
    }

    protected function role(): string
    {
        return 'Anda adalah asisten umum RuangASN yang membantu pegawai ASN dengan ramah dan ringkas.';
    }
}
