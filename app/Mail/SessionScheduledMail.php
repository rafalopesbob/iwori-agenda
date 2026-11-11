<?php

namespace App\Mail;

use App\Enums\EmailTemplateType;
use App\Models\ClientSession;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
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
        $templates = app(EmailTemplateService::class);

        $rendered = $templates->renderFor(
            $session->client->user,
            EmailTemplateType::SessionScheduled,
            $templates->sessionVariables($session),
        );

        $this->renderedSubject = $rendered['subject'];
        $this->renderedBody = $rendered['body'];
    }

    /**
     * Get the message envelope.
     *
     * Sai do endereço do sistema com o nome do profissional; as respostas
     * do cliente vão para o e-mail que o profissional escolheu no Perfil.
     */
    public function envelope(): Envelope
    {
        $professional = $this->session->client->user;

        return new Envelope(
            from: new Address(config('mail.from.address'), $professional->name),
            replyTo: [new Address($professional->replyToAddress(), $professional->name)],
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
