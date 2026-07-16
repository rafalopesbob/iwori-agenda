<?php

namespace App\Mail;

use App\Enums\EmailTemplateType;
use App\Models\ClientSession;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SessionScheduledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $renderedSubject;

    public string $renderedBody;

    /**
     * Create a new message instance.
     *
     * Assunto e corpo são renderizados aqui (momento do dispatch) a partir
     * do template do profissional, com fallback para o padrão do sistema.
     */
    public function __construct(
        public ClientSession $session,
    ) {
        $rendered = app(EmailTemplateService::class)->renderFor(
            $session->client->user,
            EmailTemplateType::SessionScheduled,
            [
                'cliente_nome' => $session->client->name,
                'profissional_nome' => $session->client->user->name,
                'data' => $session->scheduled_at->format('d/m/Y'),
                'hora' => $session->scheduled_at->format('H:i'),
                'duracao' => (string) $session->duration_minutes,
            ],
        );

        $this->renderedSubject = $rendered['subject'];
        $this->renderedBody = $rendered['body'];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renderedSubject,
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
                'professionalName' => $this->session->client->user->name,
            ],
        );
    }
}
