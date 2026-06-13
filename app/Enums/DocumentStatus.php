<?php
namespace App\Enums;
enum DocumentStatus: string {
    case DRAFT      = 'draft';
    case IN_REVIEW  = 'in_review';
    case APPROVED   = 'approved';
    case PUBLISHED  = 'published';
    case REJECTED   = 'rejected';
    case ARCHIVED   = 'archived';
    case EXPIRED    = 'expired';
    case SUPERSEDED = 'superseded';
}
