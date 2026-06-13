<?php
namespace App\Services\Ai\Agents;

use App\Enums\AiAgentType;

/**
 * Resolves the specialised AiAgent for a given AiAgentType. Agent types without
 * a dedicated implementation (document/executive/workload) fall back to the
 * GeneralAgent so the orchestrator always has a usable agent.
 */
class AgentRegistry
{
    public function __construct(
        private SecretaryAgent $secretary,
        private MeetingAgent $meeting,
        private ReportAgent $report,
        private KnowledgeAgent $knowledge,
        private PerformanceAgent $performance,
        private GeneralAgent $general,
    ) {}

    public function for(AiAgentType $type): AiAgent
    {
        return match ($type) {
            AiAgentType::SECRETARY   => $this->secretary,
            AiAgentType::MEETING     => $this->meeting,
            AiAgentType::REPORT      => $this->report,
            AiAgentType::KNOWLEDGE   => $this->knowledge,
            AiAgentType::PERFORMANCE => $this->performance,
            default                  => $this->general,
        };
    }

    public function meetingAgent(): MeetingAgent
    {
        return $this->meeting;
    }

    public function reportAgent(): ReportAgent
    {
        return $this->report;
    }
}
