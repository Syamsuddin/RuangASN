<?php
namespace App\Enums;
enum TaskPriority: string {
    case CRITICAL = 'critical';
    case HIGH     = 'high';
    case MEDIUM   = 'medium';
    case LOW      = 'low';
    case ROUTINE  = 'routine';
}
