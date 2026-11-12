<?php

namespace App\Mail;

use App\Enums\Role;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $url  accept link, built before queueing (the queue worker has no tenant)
     */
    public function __construct(
        public string $workspaceName,
        public string $inviterName,
        public Role $role,
        public string $url,
    ) {
        $this->onQueue('mail');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->inviterName} invited you to {$this->workspaceName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invitation',
        );
    }
}
