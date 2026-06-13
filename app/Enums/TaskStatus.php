<?php
namespace App\Enums;
enum TaskStatus: string {
    case DRAFT           = 'draft';
    case OPEN            = 'open';
    case ASSIGNED        = 'assigned';
    case IN_PROGRESS     = 'in_progress';
    case WAITING_REVIEW  = 'waiting_review';
    case REVISION_NEEDED = 'revision_needed';
    case COMPLETED       = 'completed';
    case CLOSED          = 'closed';
    case ARCHIVED        = 'archived';
    case CANCELLED       = 'cancelled';
}
