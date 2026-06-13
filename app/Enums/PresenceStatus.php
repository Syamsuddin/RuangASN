<?php
namespace App\Enums;
enum PresenceStatus: string {
    case ONLINE         = 'online';
    case OFFLINE        = 'offline';
    case AWAY           = 'away';
    case DO_NOT_DISTURB = 'do_not_disturb';
    case IN_MEETING     = 'in_meeting';
    case FOCUS_MODE     = 'focus_mode';
    case ON_LEAVE       = 'on_leave';
    case TRAVELING      = 'traveling';
}
