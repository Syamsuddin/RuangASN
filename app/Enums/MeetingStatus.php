<?php
namespace App\Enums;
enum MeetingStatus: string {
    case DRAFT       = 'draft';
    case SCHEDULED   = 'scheduled';
    case CONFIRMED   = 'confirmed';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED   = 'completed';
    case POSTPONED   = 'postponed';
    case CANCELLED   = 'cancelled';
    case ARCHIVED    = 'archived';
}
