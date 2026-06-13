<?php

namespace App\Enums;

enum ReportStatus: string
{
    case DRAFT      = 'draft';
    case SUBMITTED  = 'submitted';
    case IN_REVIEW  = 'in_review';
    case REVISION   = 'revision';
    case APPROVED   = 'approved';
    case PUBLISHED  = 'published';
    case ARCHIVED   = 'archived';
    case REJECTED   = 'rejected';
}
