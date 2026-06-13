<?php

namespace App\Enums;

enum PerformancePredicate: string
{
    case SANGAT_BAIK   = 'sangat_baik';
    case BAIK          = 'baik';
    case CUKUP         = 'cukup';
    case KURANG        = 'kurang';
    case SANGAT_KURANG = 'sangat_kurang';
}
