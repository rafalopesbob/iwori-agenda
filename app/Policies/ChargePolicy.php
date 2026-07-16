<?php

namespace App\Policies;

use App\Models\Charge;
use App\Models\User;

class ChargePolicy
{
    /**
     * Qualquer profissional autenticado pode listar as próprias cobranças.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Apenas o profissional dono enxerga a cobrança.
     */
    public function view(User $user, Charge $charge): bool
    {
        return $charge->user_id === $user->id;
    }

    /**
     * Apenas o profissional dono pode alterar a cobrança.
     */
    public function update(User $user, Charge $charge): bool
    {
        return $charge->user_id === $user->id;
    }
}
