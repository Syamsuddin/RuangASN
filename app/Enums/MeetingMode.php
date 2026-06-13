<?php
namespace App\Enums;
enum MeetingMode: string {
    case OFFLINE = 'offline';
    case ONLINE  = 'online';
    case HYBRID  = 'hybrid';
}
