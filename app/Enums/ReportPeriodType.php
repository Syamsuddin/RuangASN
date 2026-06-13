<?php

namespace App\Enums;

enum ReportPeriodType: string
{
    case DAILY    = 'daily';
    case WEEKLY   = 'weekly';
    case MONTHLY  = 'monthly';
    case SEMESTER = 'semester';
    case ANNUAL   = 'annual';
    case CUSTOM   = 'custom';
}
