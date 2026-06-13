<?php

namespace App\Enums;

enum SkpPerspective: string
{
    case PENERIMA_LAYANAN = 'penerima_layanan';
    case PROSES_BISNIS    = 'proses_bisnis';
    case PENGEMBANGAN     = 'pengembangan';
    case ANGGARAN         = 'anggaran';
}
