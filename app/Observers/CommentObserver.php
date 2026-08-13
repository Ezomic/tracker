<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Comment;
use App\Support\IssueSearch;

/**
 * Comments are part of what a search covers, so the issue's index row is
 * rewritten whenever the thread changes.
 */
class CommentObserver
{
    public function created(Comment $comment): void
    {
        IssueSearch::indexFor($comment);
    }

    public function updated(Comment $comment): void
    {
        IssueSearch::indexFor($comment);
    }

    public function deleted(Comment $comment): void
    {
        IssueSearch::indexFor($comment);
    }
}
