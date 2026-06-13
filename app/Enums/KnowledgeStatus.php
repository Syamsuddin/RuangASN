<?php

namespace App\Enums;

enum KnowledgeStatus: string
{
    case DRAFT     = 'draft';
    case IN_REVIEW = 'in_review';
    case PUBLISHED = 'published';
    case ARCHIVED  = 'archived';
    case OUTDATED  = 'outdated';
}
