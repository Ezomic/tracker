<?php

declare(strict_types=1);

namespace App\Enums;

enum IssueStatus: string
{
    case Backlog = 'backlog';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Done = 'done';
}
