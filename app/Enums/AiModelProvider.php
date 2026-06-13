<?php
namespace App\Enums;

enum AiModelProvider: string
{
    case GEMINI   = 'gemini';
    case CLAUDE   = 'claude';
    case OPENAI   = 'openai';
    case QWEN     = 'qwen';
    case DEEPSEEK = 'deepseek';
    case LLAMA    = 'llama';
    case MISTRAL  = 'mistral';
}
