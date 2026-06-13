<?php
namespace App\Enums;
enum UserType: string {
    case PNS       = 'pns';
    case PPPK      = 'pppk';
    case HONORER   = 'honorer';
    case OUTSOURCE = 'outsource';
    case GUEST     = 'guest';
    case AI_AGENT  = 'ai_agent';
}
