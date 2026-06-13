<?php
namespace App\Enums;

enum AiInteractionStatus: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED  = 'completed';
    case FAILED     = 'failed';
    case CANCELLED  = 'cancelled';
}
