<?php
namespace App\Enums;
enum NotificationChannel: string {
    case IN_APP   = 'in_app';
    case PUSH     = 'push';
    case EMAIL    = 'email';
    case WHATSAPP = 'whatsapp';
    case TELEGRAM = 'telegram';
    case SMS      = 'sms';
    case WEBHOOK  = 'webhook';
}
