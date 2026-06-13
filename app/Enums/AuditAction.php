<?php
namespace App\Enums;
enum AuditAction: string {
    case CREATED              = 'created';
    case UPDATED              = 'updated';
    case DELETED              = 'deleted';
    case RESTORED             = 'restored';
    case LOGIN                = 'login';
    case LOGOUT               = 'logout';
    case LOGIN_FAILED         = 'login_failed';
    case MFA_VERIFIED         = 'mfa_verified';
    case MFA_FAILED           = 'mfa_failed';
    case PASSWORD_CHANGED     = 'password_changed';
    case ACCOUNT_LOCKED       = 'account_locked';
    case ROLE_ASSIGNED        = 'role_assigned';
    case ROLE_REMOVED         = 'role_removed';
    case STATUS_CHANGED       = 'status_changed';
    case APPROVED             = 'approved';
    case REJECTED             = 'rejected';
    case SUBMITTED            = 'submitted';
    case VIEWED               = 'viewed';
    case EXPORTED             = 'exported';
    case DOWNLOADED           = 'downloaded';
    case AI_QUERIED           = 'ai_queried';
    case AI_ACTION_CONFIRMED  = 'ai_action_confirmed';
}
