<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// user_id fica fora do Fillable de propósito: o vínculo com o profissional
// deve ser feito via relacionamento ($user->clients()->create(...)), nunca
// vindo do request.
#[Fillable(['name', 'email', 'phone', 'session_value', 'notes', 'active'])]
class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'session_value' => 'decimal:2',
            'active' => 'boolean',
            'notes' => 'encrypted',
        ];
    }

    /**
     * Profissional responsável pelo cliente.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sessões de atendimento do cliente.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ClientSession::class);
    }

    /**
     * Apenas clientes ativos.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
