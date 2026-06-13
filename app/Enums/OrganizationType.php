<?php
namespace App\Enums;
enum OrganizationType: string {
    case GOVERNMENT    = 'government';
    case DEPARTMENT    = 'department';
    case UNIT          = 'unit';
    case SUB_UNIT      = 'sub_unit';
    case TEAM          = 'team';
    case COMMITTEE     = 'committee';
    case WORKING_GROUP = 'working_group';
}
