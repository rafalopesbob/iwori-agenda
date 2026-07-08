<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// client_id fica fora do Fillable: a sessão deve ser criada via
// relacionamento ($client->sessions()->create(...)).
#[Fillable(['scheduled_at', 'duration_minutes', 'status', 'value', 'notes'])]
class ClientSession extends Model
{
    /** @use HasFactory<\Database\Factories\ClientSessionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'status' => SessionStatus::class,
            'value' => 'decimal:2',
            'notes' => 'encrypted',
        ];
    }

    /**
     * Cliente da sessão.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Apenas sessões que entram no faturamento
     * (realizadas e faltas não informadas).
     */
    public function scopeBillable(Builder $query): Builder
    {
        return $query->whereIn('status', SessionStatus::billableCases());
    }

    /**
     * Sessões dentro de um intervalo (ex.: ciclo mensal de faturamento).
     */
    public function scopeScheduledBetween(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('scheduled_at', [$start, $end]);
    }

    /**
     * Demais sessões da mesma série recorrente.
     */
    public function scopeSameRecurrenceGroup(Builder $query, self $session): Builder
    {
        return $query->where('recurrence_group_id', $session->recurrence_group_id);
    }

    /**
     * Esta sessão faz parte de uma série recorrente?
     */
    public function isRecurring(): bool
    {
        return $this->recurrence_group_id !== null;
    }
}
