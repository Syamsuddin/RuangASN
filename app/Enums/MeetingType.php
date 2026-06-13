<?php
namespace App\Enums;
enum MeetingType: string {
    case INTERNAL     = 'internal';
    case CROSS_OPD    = 'cross_opd';
    case COORDINATION = 'coordination';
    case BRIEFING     = 'briefing';
    case REVIEW       = 'review';
    case EVALUATION   = 'evaluation';
    case HEARING      = 'hearing';
    case EXTERNAL     = 'external';
    case ONE_ON_ONE   = 'one_on_one';
}
