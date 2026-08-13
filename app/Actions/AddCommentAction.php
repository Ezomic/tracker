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
    public function __construct(private readonly WatchIssueAction $watch) {}

    public function handle(Issue $issue, User $author, string $body): Comment
    {
        $comment = $issue->comments()->create([
            'user_id' => $author->id,
            'body' => $body,
        ]);

        // Commenting implies interest, so it subscribes you unless you have
        // explicitly walked away from the issue.
        $this->watch->autoWatch($issue, $author);

        $this->notifyMentionsAndWatchers($issue, $comment, $author);

        return $comment;
    }

    private function notifyMentionsAndWatchers(Issue $issue, Comment $comment, User $actor): void
    {
        $excerpt = Str::limit($comment->body, 120);

        $mentioned = Mentions::membersIn($comment->body, $issue->project)
            ->reject(fn (User $member) => $member->id === $actor->id);

        Notification::send($mentioned, new IssueNotification('comment_mention', $issue, $actor, $excerpt));

        // Watchers and the assignee, minus anyone already told by name and
        // minus the person who wrote it.
        $watchers = $issue->notifiable($actor)
            ->reject(fn (User $user): bool => $mentioned->contains('id', $user->id));

        Notification::send($watchers, new IssueNotification('issue_commented', $issue, $actor, $excerpt));
    }
}
