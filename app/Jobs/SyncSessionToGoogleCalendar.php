<?php

namespace App\Jobs;

use App\Enums\SessionStatus;
use App\Models\ClientSession;
use App\Services\GoogleCalendarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class SyncSessionToGoogleCalendar implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Espera entre as tentativas, para dar folga ao limite de requisições
     * do Google quando muitas sessões são enviadas de uma vez.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ClientSession $session,
    ) {}

    /**
     * Execute the job.
     *
     * Upsert: cria o evento na primeira sincronização e atualiza o
     * existente nos reagendamentos — nunca duplica.
     */
    public function handle(GoogleCalendarService $calendar): void
    {
        // A sessão é relida do banco ao rodar o job. Se ela foi cancelada
        // enquanto o job esperava na fila, o evento não deve ser criado.
        if ($this->session->status === SessionStatus::Canceled) {
            $calendar->deleteEvent($this->session);

            return;
        }

        $this->session->google_event_id
            ? $calendar->updateEvent($this->session)
            : $calendar->createEvent($this->session);
    }
}
