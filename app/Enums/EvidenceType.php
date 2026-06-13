<?php
namespace App\Enums;
enum EvidenceType: string {
    case FILE     = 'file';
    case IMAGE    = 'image';
    case URL      = 'url';
    case TEXT     = 'text';
    case VIDEO    = 'video';
    case DOCUMENT = 'document';
    case REPORT   = 'report';
}
