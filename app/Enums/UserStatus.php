<?php
namespace App\Enums;
enum UserStatus: string {
    case ACTIVE     = 'active';
    case INACTIVE   = 'inactive';
    case SUSPENDED  = 'suspended';
    case RETIRED    = 'retired';
    case TERMINATED = 'terminated';
    case PENDING    = 'pending';
}
