<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
     * Recebe o callback OAuth e conecta o calendário do profissional.
     */
    public function callback(Request $request, GoogleCalendarService $calendar): RedirectResponse
    {
        abort_unless($calendar->isConfigured(), 404);

        // O state precisa bater com o gerado no redirect (anti-CSRF).
        $expectedState = $request->session()->pull('google_oauth_state');
        abort_if(! $expectedState || $expectedState !== $request->query('state'), 403);

        if ($request->query('error') || ! $request->query('code')) {
            return redirect()->route('dashboard')
                ->with('status', 'Conexão com o Google Calendar cancelada.');
        }

        $calendar->connect($request->user(), $request->query('code'));

        return redirect()->route('dashboard')
            ->with('status', 'Google Calendar conectado com sucesso.');
    }

    /**
     * Desconecta o Google Calendar do profissional.
     */
    public function disconnect(Request $request, GoogleCalendarService $calendar): RedirectResponse
    {
        $calendar->disconnect($request->user());

        return redirect()->route('dashboard')
            ->with('status', 'Google Calendar desconectado.');
    }
}
