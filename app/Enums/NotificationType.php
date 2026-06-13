<?php
namespace App\Enums;
enum NotificationType: string {
    case TASK_ASSIGNED    = 'task_assigned';
    case TASK_DUE         = 'task_due';
    case TASK_OVERDUE     = 'task_overdue';
    case TASK_COMPLETED   = 'task_completed';
    case MEETING_INVITE   = 'meeting_invite';
    case MEETING_REMINDER = 'meeting_reminder';
    case MEETING_STARTED  = 'meeting_started';
    case REPORT_DUE       = 'report_due';
    case APPROVAL_REQUEST = 'approval_request';
    case APPROVAL_DONE    = 'approval_done';
    case MENTION          = 'mention';
    case SYSTEM           = 'system';
    case SECURITY         = 'security';
    case AI               = 'ai';
    case ANNOUNCEMENT     = 'announcement';
    case PERFORMANCE      = 'performance';
}
