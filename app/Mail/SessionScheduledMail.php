<?php

namespace App\Mail;

use App\Models\ClientSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SessionScheduledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public ClientSession $session,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sessão confirmada para '.$this->session->scheduled_at->format('d/m/Y \à\s H:i'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.session-scheduled',
            with: [
                'clientName' => $this->session->client->name,
                'professionalName' => $this->session->client->user->name,
            ],
        );
    }
}
