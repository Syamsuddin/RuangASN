<?php
namespace App\Enums;

enum AiAgentType: string
{
    case SECRETARY   = 'secretary';
    case MEETING     = 'meeting';
    case REPORT      = 'report';
    case DOCUMENT    = 'document';
    case KNOWLEDGE   = 'knowledge';
    case PERFORMANCE = 'performance';
    case EXECUTIVE   = 'executive';
    case WORKLOAD    = 'workload';
    case GENERAL     = 'general';
}
