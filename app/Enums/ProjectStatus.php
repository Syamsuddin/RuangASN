<?php
namespace App\Enums;
enum ProjectStatus: string {
    case DRAFT      = 'draft';
    case PLANNING   = 'planning';
    case ACTIVE     = 'active';
    case ON_HOLD    = 'on_hold';
    case MONITORING = 'monitoring';
    case CLOSING    = 'closing';
    case COMPLETED  = 'completed';
    case CANCELLED  = 'cancelled';
    case ARCHIVED   = 'archived';
}
