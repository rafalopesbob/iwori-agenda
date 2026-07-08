<?php

namespace App\Models;

use App\Enums\BillingChannel;
use App\Enums\PaymentCycle;
use Carbon\CarbonImmutable;
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
#[Fillable(['name', 'email', 'phone', 'session_value', 'notes', 'active', 'payment_cycle', 'payment_day', 'payment_interval_days', 'billing_channel'])]
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
            'payment_cycle' => PaymentCycle::class,
            'billing_channel' => BillingChannel::class,
            'last_charged_at' => 'datetime',
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

    /**
     * Período coberto por uma cobrança feita na data de referência:
     * da última cobrança (exclusiva) — ou um ciclo para trás — até o
     * fim do dia de referência.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function currentChargePeriod(?CarbonImmutable $date = null): array
    {
        $end = ($date ?? CarbonImmutable::now())->endOfDay();

        $start = $this->last_charged_at
            ? CarbonImmutable::createFromInterface($this->last_charged_at)->addDay()->startOfDay()
            : $this->cycleStart($end);

        return ['start' => $start, 'end' => $end];
    }

    /**
     * A cobrança do cliente vence nesta data?
     */
    public function isChargeDueOn(CarbonImmutable $date): bool
    {
        if (! $this->active || $this->alreadyChargedOn($date)) {
            return false;
        }

        return match ($this->payment_cycle) {
            // Clamp para meses curtos: dia 31 cobra no último dia de fevereiro etc.
            PaymentCycle::Monthly => $this->payment_day !== null
                && $date->day === min($this->payment_day, $date->daysInMonth),
            PaymentCycle::Weekly => $this->dueByInterval($date, 7),
            PaymentCycle::Interval => $this->payment_interval_days
                ? $this->dueByInterval($date, $this->payment_interval_days)
                : false,
        };
    }

    /**
     * Número do WhatsApp em formato E.164 (assume Brasil quando sem DDI).
     */
    public function whatsappNumber(): ?string
    {
        if (! $this->phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $this->phone);

        if ($digits === '' || strlen($digits) < 10) {
            return null;
        }

        return str_starts_with($digits, '55') && strlen($digits) >= 12
            ? $digits
            : '55'.$digits;
    }

    /**
     * Início do período quando o cliente nunca foi cobrado: um ciclo para trás.
     */
    protected function cycleStart(CarbonImmutable $end): CarbonImmutable
    {
        return match ($this->payment_cycle) {
            PaymentCycle::Weekly => $end->subWeek()->addDay()->startOfDay(),
            PaymentCycle::Monthly => $end->subMonth()->addDay()->startOfDay(),
            PaymentCycle::Interval => $end->subDays(max(1, (int) $this->payment_interval_days))->addDay()->startOfDay(),
        };
    }

    /**
     * Evita cobrança dupla no mesmo dia (reexecução do agendador).
     */
    protected function alreadyChargedOn(CarbonImmutable $date): bool
    {
        return $this->last_charged_at !== null
            && $this->last_charged_at->isSameDay($date);
    }

    /**
     * Ciclos por intervalo: vence quando passou o intervalo desde a última
     * cobrança; sem cobrança anterior, vence se há sessões faturáveis.
     */
    protected function dueByInterval(CarbonImmutable $date, int $days): bool
    {
        if ($this->last_charged_at === null) {
            return $this->sessions()
                ->billable()
                ->where('scheduled_at', '<=', $date->endOfDay())
                ->exists();
        }

        return CarbonImmutable::createFromInterface($this->last_charged_at)
            ->addDays($days)
            ->lessThanOrEqualTo($date->endOfDay());
    }
}
