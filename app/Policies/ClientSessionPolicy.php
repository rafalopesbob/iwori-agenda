<?php

namespace App\Policies;

use App\Models\ClientSession;
use App\Models\User;

class ClientSessionPolicy
{
    /**
     * Apenas o profissional responsável pelo cliente enxerga a sessão.
     */
    public function view(User $user, ClientSession $session): bool
    {
        return $session->client->user_id === $user->id;
    }

    /**
     * Apenas o profissional responsável pode alterar a sessão.
     */
    public function update(User $user, ClientSession $session): bool
    {
        return $session->client->user_id === $user->id;
    }

    /**
     * Apenas o profissional responsável pode excluir a sessão.
     */
    public function delete(User $user, ClientSession $session): bool
    {
        return $session->client->user_id === $user->id;
    }
}
