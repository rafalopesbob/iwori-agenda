<?php

namespace App\Http\Controllers;

use App\Jobs\SendClientCharge;
use App\Jobs\SendSessionCharge;
use App\Models\Client;
use App\Models\ClientSession;
use Illuminate\Http\RedirectResponse;

class ChargeController extends Controller
{
    /**
     * Cobrança manual do período corrente do cliente.
     */
    public function client(Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        SendClientCharge::dispatch($client);

        return back()->with('status', 'Cobrança de '.$client->name.' enviada para processamento.');
    }

    /**
     * Cobrança/lembrete avulso de uma sessão.
     */
    public function session(ClientSession $session): RedirectResponse
    {
        $this->authorize('update', $session);

        SendSessionCharge::dispatch($session);

        return back()->with('status', 'Cobrança da sessão de '.$session->client->name.' enviada para processamento.');
    }
}
