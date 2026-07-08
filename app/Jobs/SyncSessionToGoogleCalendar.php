<?php

namespace App\Jobs;

use App\Models\ClientSession;
use App\Services\GoogleCalendarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class SyncSessionToGoogleCalendar implements ShouldQueue
{
    use Queueable, SerializesModels;

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
        $this->session->google_event_id
            ? $calendar->updateEvent($this->session)
            : $calendar->createEvent($this->session);
    }
}
