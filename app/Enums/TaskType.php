<?php
namespace App\Enums;
enum TaskType: string {
    case PERSONAL    = 'personal';
    case TEAM        = 'team';
    case PROJECT     = 'project';
    case MEETING     = 'meeting';
    case DISPOSITION = 'disposition';
    case ROUTINE     = 'routine';
    case RECURRING   = 'recurring';
    case SKP         = 'skp';
    case APPROVAL    = 'approval';
}
