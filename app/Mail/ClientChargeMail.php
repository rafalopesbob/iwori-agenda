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
     * @param Collection<int, \App\Models\ClientSession> $sessions
     */
    public function __construct(
        public Client $client,
        public Collection $sessions,
        public float $total,
        public CarbonInterface $start,
        public CarbonInterface $end,
    ) {
        $rendered = app(EmailTemplateService::class)->renderFor(
            $client->user,
            EmailTemplateType::Charge,
            [
                'cliente_nome' => $client->name,
                'profissional_nome' => $client->user->name,
                'valor' => 'R$ '.number_format($total, 2, ',', '.'),
                'periodo_inicio' => $start->format('d/m/Y'),
                'periodo_fim' => $end->format('d/m/Y'),
                'periodo' => $start->format('d/m/Y').' a '.$end->format('d/m/Y'),
                'lista_sessoes' => $sessions
                    ->map(fn (ClientSession $session) => sprintf(
                        '%s — %s — R$ %s',
                        $session->scheduled_at->format('d/m/Y H:i'),
                        $session->status->label(),
                        number_format((float) $session->value, 2, ',', '.'),
                    ))
                    ->implode("\n"),
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
            markdown: 'emails.client-charge',
            with: [
                'professionalName' => $this->client->user->name,
            ],
        );
    }
}
