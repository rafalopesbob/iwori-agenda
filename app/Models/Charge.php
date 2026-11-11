<?php

namespace App\Models;

use App\Enums\BillingChannel;
use App\Enums\ChargeStatus;
use Database\Factories\ChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// user_id, client_id, client_session_id, paid_at e receipt_path ficam fora
// do Fillable de propósito: são definidos por código, nunca vindos do request.
#[Fillable(['period_start', 'period_end', 'amount', 'status', 'channel', 'sent_at', 'notes'])]
class Charge extends Model
{
    /** @use HasFactory<ChargeFactory> */
    use HasFactory;

    /**
     * Dias de pendência a partir do envio para considerar a cobrança atrasada.
     */
    public const OVERDUE_AFTER_DAYS = 7;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'amount' => 'decimal:2',
            'status' => ChargeStatus::class,
            'channel' => BillingChannel::class,
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'notes' => 'encrypted',
            'emails_sent' => 'integer',
            'email_queued_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'email_failed_at' => 'datetime',
        ];
    }

    /**
     * Cobrança pendente há mais tempo que o limite conta como atrasada.
     */
    public function isOverdue(): bool
    {
        return $this->status === ChargeStatus::Pending
            && $this->sent_at !== null
            && $this->sent_at->lt(now()->subDays(self::OVERDUE_AFTER_DAYS));
    }

    /**
     * Situação do último e-mail desta cobrança: 'sent' (saiu), 'queued'
     * (na fila), 'failed' (falhou) ou null quando nenhum e-mail foi para a
     * fila (cobrança só por WhatsApp, cliente sem e-mail ou anterior ao
     * acompanhamento de envio).
     */
    public function emailStatus(): ?string
    {
        return match (true) {
            $this->email_queued_at === null => null,
            $this->email_failed_at !== null => 'failed',
            $this->email_sent_at !== null && $this->email_sent_at->gte($this->email_queued_at) => 'sent',
            default => 'queued',
        };
    }

    /**
     * Pendentes há mais de OVERDUE_AFTER_DAYS dias (mesma regra de isOverdue).
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', ChargeStatus::Pending->value)
            ->where('sent_at', '<', now()->subDays(self::OVERDUE_AFTER_DAYS));
    }

    /**
     * Profissional dono da cobrança.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cliente cobrado. Inclui clientes removidos (soft delete) para que o
     * histórico de cobranças continue mostrando o nome.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * Sessão vinculada, quando cobrança avulsa.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ClientSession::class, 'client_session_id');
    }

    /**
     * Somente cobranças pendentes.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ChargeStatus::Pending->value);
    }

    /**
     * Somente cobranças pagas.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', ChargeStatus::Paid->value);
    }
}
