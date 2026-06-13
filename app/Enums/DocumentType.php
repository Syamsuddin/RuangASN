<?php
namespace App\Enums;

enum DocumentType: string {
    case LETTER          = 'letter';
    case REGULATION      = 'regulation';
    case SOP             = 'sop';
    case REPORT          = 'report';
    case MINUTES         = 'minutes';
    case DECISION        = 'decision';
    case MEMO            = 'memo';
    case TEMPLATE        = 'template';
    case REFERENCE       = 'reference';
    case CONTRACT        = 'contract';
    case PROJECT_DOC     = 'project_doc';
    case PERFORMANCE_DOC = 'performance_doc';
}
