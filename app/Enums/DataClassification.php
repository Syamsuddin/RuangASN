<?php
namespace App\Enums;
enum DataClassification: int {
    case PUBLIC       = 1;
    case INTERNAL     = 2;
    case CONFIDENTIAL = 3;
    case RESTRICTED   = 4;
}
