<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to {$this->invitation->project->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.project-invitation',
            with: [
                'projectName' => $this->invitation->project->name,
                'roleLabel' => $this->invitation->role->label(),
                'inviterName' => $this->invitation->invitedBy?->name,
                'acceptUrl' => $this->acceptUrl,
            ],
        );
    }
}
