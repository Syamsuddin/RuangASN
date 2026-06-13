<?php
namespace App\Enums;

/**
 * Hasil IntentClassifier (design enum, bukan kolom DB langsung —
 * disimpan sebagai string di ai_interaction_logs.intent).
 */
enum AiIntent: string
{
    case CREATE_TASK       = 'create_task';
    case SCHEDULE_MEETING  = 'schedule_meeting';
    case GENERATE_REPORT   = 'generate_report';
    case SUMMARIZE_MEETING = 'summarize_meeting';
    case KNOWLEDGE_QA      = 'knowledge_qa';
    case PERFORMANCE_QUERY = 'performance_query';
    case GENERAL_CHAT      = 'general_chat';
}
