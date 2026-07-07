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
     */
    public function handle(GoogleCalendarService $calendar): void
    {
        $calendar->createEvent($this->session);
    }
}
