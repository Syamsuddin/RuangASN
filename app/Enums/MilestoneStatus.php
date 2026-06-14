<?php
namespace App\Enums;

enum MilestoneStatus: string
{
    case PENDING     = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED   = 'completed';
    case MISSED      = 'missed';     // Lewat deadline
    case SKIPPED     = 'skipped';    // Dilewati (tidak relevan)
}
