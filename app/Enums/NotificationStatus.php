<?php
namespace App\Enums;
enum NotificationStatus: string {
    case PENDING   = 'pending';
    case SENT      = 'sent';
    case DELIVERED = 'delivered';
    case READ      = 'read';
    case FAILED    = 'failed';
    case CANCELLED = 'cancelled';
}
