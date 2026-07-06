<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    /**
     * Lista os clientes do profissional autenticado.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Client::class);

        $clients = $request->user()
            ->clients()
            ->orderBy('name')
            ->paginate(15);

        return view('clients.index', compact('clients'));
    }

    /**
     * Exibe o formulário de cadastro.
     */
    public function create(): View
    {
        $this->authorize('create', Client::class);

        return view('clients.create');
    }

    /**
     * Cadastra um novo cliente vinculado ao profissional autenticado.
     */
    public function store(StoreClientRequest $request): RedirectResponse
    {
        $request->user()->clients()->create($request->validated());

        return redirect()->route('clients.index')
            ->with('status', 'Cliente cadastrado com sucesso.');
    }

    /**
     * Exibe os dados de um cliente.
     */
    public function show(Client $client): View
    {
        $this->authorize('view', $client);

        return view('clients.show', compact('client'));
    }

    /**
     * Exibe o formulário de edição.
     */
    public function edit(Client $client): View
    {
        $this->authorize('update', $client);

        return view('clients.edit', compact('client'));
    }

    /**
     * Atualiza os dados do cliente.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()->route('clients.index')
            ->with('status', 'Cliente atualizado com sucesso.');
    }

    /**
     * Remove o cliente (soft delete, preservando o histórico).
     */
    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return redirect()->route('clients.index')
            ->with('status', 'Cliente removido com sucesso.');
    }
}
