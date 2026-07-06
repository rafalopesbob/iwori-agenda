<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    /**
     * Qualquer profissional autenticado pode listar os próprios clientes.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Apenas o profissional responsável enxerga o cliente.
     */
    public function view(User $user, Client $client): bool
    {
        return $client->user_id === $user->id;
    }

    /**
     * Qualquer profissional autenticado pode cadastrar clientes.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Apenas o profissional responsável pode alterar o cliente.
     */
    public function update(User $user, Client $client): bool
    {
        return $client->user_id === $user->id;
    }

    /**
     * Apenas o profissional responsável pode excluir o cliente.
     */
    public function delete(User $user, Client $client): bool
    {
        return $client->user_id === $user->id;
    }
}
