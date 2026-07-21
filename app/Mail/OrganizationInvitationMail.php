<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to {$this->invitation->organization->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.organization-invitation',
            with: [
                'organizationName' => $this->invitation->organization->name,
                'roleLabel' => $this->invitation->role->label(),
                'projectName' => $this->invitation->project?->name,
                'inviterName' => $this->invitation->invitedBy?->name,
                'acceptUrl' => $this->acceptUrl,
            ],
        );
    }
}
