<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\User;

class EmailTemplatePolicy
{
    /**
     * Qualquer profissional autenticado pode listar os próprios templates.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Apenas o dono enxerga o template.
     */
    public function view(User $user, EmailTemplate $emailTemplate): bool
    {
        return $emailTemplate->user_id === $user->id;
    }

    /**
     * Qualquer profissional autenticado pode criar templates.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Apenas o dono pode alterar o template.
     */
    public function update(User $user, EmailTemplate $emailTemplate): bool
    {
        return $emailTemplate->user_id === $user->id;
    }

    /**
     * Apenas o dono pode excluir o template.
     */
    public function delete(User $user, EmailTemplate $emailTemplate): bool
    {
        return $emailTemplate->user_id === $user->id;
    }
}
