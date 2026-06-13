<?php
namespace App\Enums;

enum CalendarType: string {
    case PERSONAL    = 'personal';
    case TEAM        = 'team';
    case PROJECT     = 'project';
    case MEETING     = 'meeting';
    case ORG         = 'org';
    case GOVERNMENT  = 'government';
    case HOLIDAY     = 'holiday';
}
