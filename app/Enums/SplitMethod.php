<?php

declare(strict_types=1);

namespace App\Enums;

enum SplitMethod: string
{
    case Equal = 'equal';
    case Exact = 'exact';
    case Percent = 'percent';
}
