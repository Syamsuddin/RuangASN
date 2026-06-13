<?php
namespace App\Enums;
enum AttendanceStatus: string {
    case INVITED    = 'invited';
    case ACCEPTED   = 'accepted';
    case DECLINED   = 'declined';
    case PRESENT    = 'present';
    case ABSENT     = 'absent';
    case LATE       = 'late';
    case LEFT_EARLY = 'left_early';
    case DELEGATED  = 'delegated';
}
