<?php
namespace App\Enums;

/**
 * Tipe aksi yang AI usulkan (proposed_actions) — disimpan, tidak dieksekusi
 * sampai user mengonfirmasi melalui confirmAction (AXIOM-04).
 */
enum ProposedActionType: string
{
    case CREATE_TASK           = 'create_task';
    case SCHEDULE_MEETING      = 'schedule_meeting';
    case GENERATE_REPORT_DRAFT = 'generate_report_draft';
    case ADD_CALENDAR_EVENT    = 'add_calendar_event';
}
