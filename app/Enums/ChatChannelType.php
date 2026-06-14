<?php
namespace App\Enums;

enum ChatChannelType: string
{
    case DM               = 'dm';
    case GROUP            = 'group';
    case TEAM_CHANNEL     = 'team_channel';
    case PROJECT_CHANNEL  = 'project_channel';
    case MEETING_CHAT     = 'meeting_chat';
    case ANNOUNCEMENT     = 'announcement';
}
