<?php

namespace App\Jobs;

use App\Models\ClientSession;
use App\Services\GoogleCalendarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class RemoveSessionFromGoogleCalendar implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Espera entre as tentativas (limite de requisições do Google).
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
     */
    public function handle(GoogleCalendarService $calendar): void
    {
        $calendar->deleteEvent($this->session);
    }
}
