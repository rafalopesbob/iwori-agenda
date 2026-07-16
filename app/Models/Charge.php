<?php

namespace App\Models;

use App\Enums\BillingChannel;
use App\Enums\ChargeStatus;
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
    /** @use HasFactory<\Database\Factories\ChargeFactory> */
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
     * Profissional dono da cobrança.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cliente cobrado.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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
