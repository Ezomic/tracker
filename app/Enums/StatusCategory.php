<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The fixed, app-reasoned meaning behind a workflow state. Board lanes are
 * freely named, but every lane maps to one of these so metrics, closed_at and
 * filters keep working regardless of a project type's lane labels.
 */
enum StatusCategory: string
{
    case Backlog = 'backlog';
    case Unstarted = 'unstarted';
    case Started = 'started';
    case Completed = 'completed';
    case Canceled = 'canceled';
}
