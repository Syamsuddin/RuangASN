<?php

namespace App\Enums;

enum ReportType: string
{
    case DAILY       = 'daily';
    case WEEKLY      = 'weekly';
    case MONTHLY     = 'monthly';
    case QUARTERLY   = 'quarterly';
    case ANNUAL      = 'annual';
    case ACTIVITY    = 'activity';
    case PROJECT     = 'project';
    case PERFORMANCE = 'performance';
    case FINANCIAL   = 'financial';
    case SPECIAL     = 'special';
}
