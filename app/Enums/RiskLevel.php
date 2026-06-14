<?php
namespace App\Enums;

enum RiskLevel: string
{
    case CRITICAL = 'critical'; // Risiko sangat tinggi
    case HIGH     = 'high';
    case MEDIUM   = 'medium';
    case LOW      = 'low';
    case NONE     = 'none';
}
