<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Queued so the send happens off-request: it keeps the sign-in response time
// constant whether or not the email exists, closing a timing side-channel that
// could otherwise be used to enumerate accounts.
class LoginCodeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $code)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your login code: {$this->code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.login-code',
        );
    }
}
