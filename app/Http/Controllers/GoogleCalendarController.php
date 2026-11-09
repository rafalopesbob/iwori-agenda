<?php

namespace App\Http\Controllers;

use App\Jobs\SyncUpcomingSessionsToGoogleCalendar;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GoogleCalendarController extends Controller
{
    /**
     * Redireciona para a tela de consentimento do Google.
     */
    public function redirect(Request $request, GoogleCalendarService $calendar): RedirectResponse
    {
        abort_unless($calendar->isConfigured(), 404);

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away($calendar->authUrl($state));
    }

    /**
     * Recebe o callback OAuth, conecta o calendário do profissional e
     * envia para o Google as sessões que já estavam marcadas.
     */
    public function callback(Request $request, GoogleCalendarService $calendar): RedirectResponse
    {
        abort_unless($calendar->isConfigured(), 404);

        // O state precisa bater com o gerado no redirect (anti-CSRF).
        $expectedState = $request->session()->pull('google_oauth_state');
        abort_if(! $expectedState || $expectedState !== $request->query('state'), 403);

        if ($request->query('error') || ! $request->query('code')) {
            return redirect()->route('dashboard')
                ->with('status', 'Conexão com o Google Agenda cancelada.');
        }

        if (! $calendar->connect($request->user(), $request->query('code'))) {
            return redirect()->route('help.google-calendar')
                ->with('status', 'Faltou a permissão da agenda. Conecte de novo e deixe marcada a opção de ver e editar os eventos.');
        }

        SyncUpcomingSessionsToGoogleCalendar::dispatch($request->user());

        return redirect()->route('dashboard')
            ->with('status', 'Google Agenda conectado. Suas próximas sessões estão sendo enviadas para lá.');
    }

    /**
     * Desconecta o Google Calendar do profissional.
     */
    public function disconnect(Request $request, GoogleCalendarService $calendar): RedirectResponse
    {
        $calendar->disconnect($request->user());

        return redirect()->route('dashboard')
            ->with('status', 'Google Agenda desconectado. Os eventos já criados continuam na sua agenda do Google.');
    }

    /**
     * Passo a passo para o profissional conectar o Google Agenda.
     */
    public function help(Request $request, GoogleCalendarService $calendar): View
    {
        $steps = [
            ['title' => 'Clique em Conectar', 'text' => 'No menu lateral, no quadro Google Agenda, clique em Conectar. No celular, o quadro fica no Painel.'],
            ['title' => 'Escolha a conta Google', 'text' => 'Escolha a conta em que você quer ver as sessões. De preferência, a mesma que você usa no celular.'],
        ];

        if (! config('services.google.verified')) {
            $steps[] = [
                'title' => 'Se aparecer um aviso, continue',
                'text' => 'O Google pode mostrar "O Google não verificou este app". Clique em Avançado e depois no link para continuar até a Agenda Iwori. O aviso aparece porque o Iwori ainda não passou pela verificação do Google, não por problema na sua conta.',
            ];
        }

        $steps[] = ['title' => 'Autorize o acesso aos eventos', 'text' => 'Na tela de permissões, deixe marcada a opção de ver e editar os eventos das suas agendas e clique em Continuar. Sem ela, a conexão não é concluída.'];
        $steps[] = ['title' => 'Pronto', 'text' => 'Você volta ao Painel com a mensagem "Google Agenda conectado" e o ponto do quadro fica verde. Em poucos minutos, as próximas sessões aparecem no Google.'];

        $timezone = $calendar->timezone() === 'America/Sao_Paulo' ? 'de Brasília' : $calendar->timezone();

        $troubleshooting = [
            'Não encontro o quadro Google Agenda' => 'A integração ainda não foi ativada no servidor. Fale com o administrador do sistema.',
            'Apareceu "Conexão com o Google Agenda cancelada"' => 'A tela do Google foi fechada ou o acesso foi negado. Clique em Conectar e tente de novo.',
            'Apareceu "Faltou a permissão da agenda"' => 'Na tela do Google, a opção de ver e editar os eventos ficou desmarcada. Conecte de novo e deixe-a marcada.',
            'As sessões não aparecem no Google' => 'Confira se o Google Agenda está aberto na mesma conta que você conectou e se a agenda principal está visível. Depois de conectar, o envio leva alguns minutos.',
            'O horário aparece diferente' => "O Iwori envia os horários no fuso {$timezone}. No Google Agenda, veja em Configurações → Geral → Fuso horário se a sua agenda está no mesmo fuso.",
        ];

        return view('help.google-calendar', [
            'configured' => $calendar->isConfigured(),
            'connected' => $request->user()->hasGoogleCalendar(),
            'steps' => array_combine(range(1, count($steps)), $steps),
            'troubleshooting' => $troubleshooting,
        ]);
    }
}
