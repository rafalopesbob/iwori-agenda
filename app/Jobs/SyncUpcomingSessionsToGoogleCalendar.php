<?php

namespace App\Jobs;

use App\Enums\SessionStatus;
use App\Models\ClientSession;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

/**
 * Envia para o Google Agenda as sessões já marcadas, de hoje em diante,
 * logo depois que o profissional conecta a conta. Sem isso, só as sessões
 * criadas ou alteradas depois da conexão apareceriam no Google.
 *
 * Cada sessão vira um job próprio: uma falha no Google afeta só aquela
 * sessão, e o upsert do SyncSessionToGoogleCalendar evita duplicar eventos
 * numa reconexão.
 */
class SyncUpcomingSessionsToGoogleCalendar implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function handle(GoogleCalendarService $calendar): void
    {
        if (! $calendar->isConfigured() || ! $this->user->hasGoogleCalendar()) {
            return;
        }

        ClientSession::query()
            ->whereHas('client', fn ($query) => $query->where('user_id', $this->user->id))
            ->where('status', SessionStatus::Scheduled)
            // Os horários são gravados no fuso local do consultório (ver GoogleCalendarService).
            ->where('scheduled_at', '>=', now($calendar->timezone())->startOfDay())
            ->lazyById()
            ->each(fn (ClientSession $session) => SyncSessionToGoogleCalendar::dispatch($session));
    }
}
