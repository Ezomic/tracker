<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Comment;
use App\Models\Issue;
use App\Models\User;
use App\Notifications\IssueNotification;
use App\Support\Mentions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class AddCommentAction
{
    public function handle(Issue $issue, User $author, string $body): Comment
    {
        $comment = $issue->comments()->create([
            'user_id' => $author->id,
            'body' => $body,
        ]);

        $this->notifyMentionsAndAssignee($issue, $comment, $author);

        return $comment;
    }

    private function notifyMentionsAndAssignee(Issue $issue, Comment $comment, User $actor): void
    {
        $excerpt = Str::limit($comment->body, 120);

        $mentioned = Mentions::membersIn($comment->body, $issue->project)
            ->reject(fn (User $member) => $member->id === $actor->id);

        Notification::send($mentioned, new IssueNotification('comment_mention', $issue, $actor, $excerpt));

        $assignee = $issue->assignee;

        if ($assignee !== null
            && $assignee->id !== $actor->id
            && ! $mentioned->contains('id', $assignee->id)) {
            $assignee->notify(new IssueNotification('issue_commented', $issue, $actor, $excerpt));
        }
    }
}
