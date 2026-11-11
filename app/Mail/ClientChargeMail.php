<?php

namespace App\Mail;

use App\Enums\EmailTemplateType;
use App\Models\Client;
use App\Models\ClientSession;
use App\Services\EmailTemplateService;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ClientChargeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $renderedSubject;

    public string $renderedBody;

    /**
     * Create a new message instance.
     *
     * Assunto e corpo são renderizados aqui (momento do dispatch) a partir
     * do template do profissional, com fallback para o padrão do sistema.
     *
     * @param  Collection<int, ClientSession>  $sessions
     */
    public function __construct(
        public Client $client,
        public Collection $sessions,
        public float $total,
        public CarbonInterface $start,
        public CarbonInterface $end,
    ) {
        $templates = app(EmailTemplateService::class);

        $rendered = $templates->renderFor(
            $client->user,
            EmailTemplateType::Charge,
            $templates->chargeVariables($client, $sessions, $total, $start, $end),
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
        $professional = $this->client->user;

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
            markdown: 'emails.client-charge',
            with: [
                'professionalName' => $this->client->user->name,
            ],
        );
    }
}
