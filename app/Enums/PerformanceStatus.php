<?php

namespace App\Enums;

enum PerformanceStatus: string
{
    case PLANNING   = 'planning';
    case ACTIVE     = 'active';
    case EVALUATING = 'evaluating';
    case FINALIZED  = 'finalized';
    case ARCHIVED   = 'archived';
}
