<?php
namespace App\Enums;
enum DelegationType: string {
    case PLT            = 'plt';
    case PLH            = 'plh';
    case SPECIAL_DUTY   = 'special_duty';
    case APPROVAL_ONLY  = 'approval_only';
    case FULL_AUTHORITY = 'full_authority';
}
