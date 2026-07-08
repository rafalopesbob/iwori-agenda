<?php

namespace App\Mail;

use App\Models\Client;
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

    /**
     * Create a new message instance.
     *
     * @param Collection<int, \App\Models\ClientSession> $sessions
     */
    public function __construct(
        public Client $client,
        public Collection $sessions,
        public float $total,
        public CarbonInterface $start,
        public CarbonInterface $end,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Valor a pagar: R$ %s — período %s a %s',
                number_format($this->total, 2, ',', '.'),
                $this->start->format('d/m'),
                $this->end->format('d/m/Y'),
            ),
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
