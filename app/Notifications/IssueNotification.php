<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Notifications\Notification;

class IssueNotification extends Notification
{
    public function __construct(
        public string $type,
        public Issue $issue,
        public User $actor,
        public ?string $excerpt = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'issueIdentifier' => $this->issue->identifier,
            'issueTitle' => $this->issue->title,
            'actorName' => $this->actor->name,
            'excerpt' => $this->excerpt,
        ];
    }
}
