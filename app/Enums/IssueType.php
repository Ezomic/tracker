<?php

declare(strict_types=1);

namespace App\Enums;

enum IssueType: string
{
    case Feature = 'feature';
    case Fix = 'fix';
}
