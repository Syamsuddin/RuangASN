<?php

namespace App\Enums;

enum KnowledgeType: string
{
    case WIKI              = 'wiki';
    case FAQ               = 'faq';
    case SOP               = 'sop';
    case BEST_PRACTICE     = 'best_practice';
    case LESSON_LEARNED    = 'lesson_learned';
    case GLOSSARY          = 'glossary';
    case REGULATION_NOTE   = 'regulation_note';
    case TEMPLATE          = 'template';
    case DIRECTORY         = 'directory';
}
