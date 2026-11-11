<?php

namespace Database\Seeders;

use App\Enums\BillingChannel;
use App\Enums\ChargeStatus;
use App\Enums\PaymentCycle;
use App\Enums\SessionStatus;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\EmailTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Massa de dados fictícia para apresentações: uma profissional com agenda,
 * presenças, faturamento e cobranças coerentes com a data de hoje.
 *
 * Uso: php artisan db:seed --class=DemoSeeder
 * Pode rodar de novo: a conta de demonstração é recriada do zero.
 * Login: katia.andrade@example.com, com a senha padrão da UserFactory.
 */
class DemoSeeder extends Seeder
{
    public const EMAIL = 'katia.andrade@example.com';

    /**
     * Agenda semanal de cada cliente: [nome, valor, ciclo, dia/intervalo, canal,
     * dia da semana (1 = segunda, numa semana em que hoje é quinta), horário,
     * duração, a cada N semanas, ativo]. Veja weekdayFor().
     */
    protected const CLIENTS = [
        ['Ana Beatriz Souza', 200, 'weekly', null, 'email', 4, '08:00', 50, 1, true],
        ['Carlos Mendes', 180, 'weekly', null, 'whatsapp', 4, '10:00', 50, 1, true],
        ['Juliana Prado', 220, 'monthly', 10, 'email', 4, '14:00', 50, 1, true],
        ['Pedro Henrique Alves', 200, 'interval', 14, 'both', 4, '16:30', 50, 2, true],
        ['Mariana Costa', 250, 'monthly', 5, 'whatsapp', 4, '18:00', 50, 1, true],
        ['Lúcia Ferreira', 180, 'monthly', 30, 'email', 1, '15:00', 50, 1, true],
        ['Rafael Lima', 220, 'monthly', 10, 'email', 1, '17:00', 50, 1, true],
        ['Beatriz Nunes', 200, 'weekly', null, 'whatsapp', 2, '09:00', 50, 1, true],
        ['Tiago Ramos', 180, 'interval', 14, 'email', 2, '11:00', 50, 2, true],
        ['Fernanda Oliveira', 240, 'monthly', 1, 'both', 3, '08:30', 50, 1, true],
        ['Gustavo Pereira', 190, 'monthly', 15, 'email', 3, '13:00', 50, 1, true],
        ['Camila Rocha', 210, 'weekly', null, 'email', 5, '09:00', 50, 1, true],
        ['Grupo de estudos (terça)', 90, 'monthly', 1, 'email', 2, '18:30', 90, 1, true],
        ['Turma de desenho', 120, 'monthly', 5, 'email', 3, '19:00', 90, 1, true],
        ['Oficina de aquarela', 150, 'monthly', 20, 'whatsapp', 6, '10:00', 120, 2, true],
        ['Roberto Almeida', 200, 'monthly', 5, 'email', 5, '15:00', 50, 1, false],
        ['Patrícia Gomes', 180, 'weekly', null, 'email', 1, '10:00', 50, 1, false],
    ];

    /**
     * Status de hoje, para o painel abrir com presenças a registrar.
     */
    protected const TODAY_STATUS = [
        'Ana Beatriz Souza' => SessionStatus::Completed,
        'Juliana Prado' => SessionStatus::NoShowExcused,
    ];

    /**
     * Clientes cuja última cobrança segue em aberto (a de Rafael já atrasada).
     */
    protected const UNPAID = ['Lúcia Ferreira', 'Rafael Lima'];

    public function run(): void
    {
        // A conta demo usa senha conhecida: nunca deve existir num banco de produção
        // real. A exceção é o ambiente de demonstração, com banco próprio (DEMO_MODE).
        if (app()->isProduction() && ! config('demo.enabled')) {
            throw new \RuntimeException('DemoSeeder não roda em produção (APP_ENV=production) fora do ambiente de demonstração (DEMO_MODE=true).');
        }

        mt_srand(2026);

        $today = CarbonImmutable::today();
        $from = $today->subMonthsNoOverflow(2)->startOfMonth();
        $until = $today->addWeeks(3);

        User::where('email', self::EMAIL)->delete();

        $user = User::factory()->create([
            'name' => 'Kátia Andrade',
            'email' => self::EMAIL,
        ]);

        foreach (self::CLIENTS as $index => [$name, $value, $cycle, $cycleValue, $channel, $weekday, $time, $duration, $every, $active]) {
            $client = Client::factory()->for($user)->create([
                'name' => $name,
                'email' => Str::slug($name, '.').'@example.com',
                'phone' => sprintf('(11) 90000-%04d', $index + 1),
                'session_value' => $value,
                'notes' => null,
                'active' => $active,
                'payment_cycle' => PaymentCycle::from($cycle),
                'payment_day' => $cycle === 'monthly' ? $cycleValue : null,
                'payment_interval_days' => $cycle === 'interval' ? $cycleValue : null,
                'billing_channel' => BillingChannel::from($channel),
            ]);

            // Cadastros espalhados antes do início da agenda, como numa carteira real.
            $registeredAt = $from->subDays(20 + $index * 13)->setTime(10, 0);
            $client->forceFill(['created_at' => $registeredAt, 'updated_at' => $registeredAt])->save();

            if ($client->billing_channel !== BillingChannel::Email) {
                $client->forceFill(['whatsapp_opt_in_at' => $registeredAt])->save();
            }

            // Clientes inativos encerraram o acompanhamento no fim do primeiro mês.
            $lastDay = $active ? $until : $from->endOfMonth();
            $weekday = $this->weekdayFor($weekday, $today->dayOfWeekIso);
            $sessions = $this->seedSessions($client, $from, $lastDay, $today, $weekday, $time, $duration, $every);

            $this->seedCharges($client, $sessions, $today, $from);
        }

        $this->seedSessionCharge($user, $today);

        EmailTemplate::factory()->for($user)->charge()->create([
            'name' => 'Cobrança com PIX',
            'subject' => 'Resumo do seu acompanhamento — {{periodo}}',
            'body' => "Olá, {{cliente_nome}}!\n\nSegue o resumo das sessões de {{periodo}}:\n\n{{lista_sessoes}}\n\nValor total: {{valor}}\nPIX: [CHAVE PIX DA PROFISSIONAL]\n\nQualquer dúvida, é só responder este e-mail.\n{{profissional_nome}}",
        ]);
    }

    /**
     * A agenda foi desenhada com a quinta-feira como o dia mais cheio. Gira os
     * dias para que esse dia caia sempre em hoje, e o painel abra com sessões
     * em qualquer dia da apresentação. Em dia útil, gira só de segunda a
     * sexta (o sábado fica no sábado); no fim de semana, gira a semana toda.
     */
    protected function weekdayFor(int $weekday, int $todayIso): int
    {
        if ($todayIso <= 5) {
            return $weekday > 5 ? $weekday : (($weekday - 4 + $todayIso - 1) % 5 + 5) % 5 + 1;
        }

        return (($weekday - 4 + $todayIso - 1) % 7 + 7) % 7 + 1;
    }

    /**
     * Sessões recorrentes do cliente no intervalo, com presenças sorteadas no passado.
     *
     * @return Collection<int, ClientSession>
     */
    protected function seedSessions(Client $client, CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $today, int $weekday, string $time, int $duration, int $every): Collection
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $group = (string) Str::ulid();

        // Alinha séries quinzenais para caírem na semana atual.
        $first = $from->next($weekday === 7 ? 0 : $weekday)->subWeek();
        if ($every > 1) {
            while ((int) $first->diffInWeeks($today->startOfWeek(CarbonInterface::MONDAY)->addDays($weekday - 1), true) % $every !== 0) {
                $first = $first->addWeek();
            }
        }

        $sessions = collect();
        for ($date = $first; $date->lte($until); $date = $date->addWeeks($every)) {
            if ($date->lt($from)) {
                continue;
            }

            $session = (new ClientSession([
                'scheduled_at' => $date->setTime($hour, $minute),
                'duration_minutes' => $duration,
                'value' => $client->session_value,
                'status' => $this->statusFor($client, $date, $today),
            ]))->forceFill([
                'client_id' => $client->id,
                'recurrence_group_id' => $group,
            ]);
            $session->save();

            $sessions->push($session);
        }

        return $sessions;
    }

    protected function statusFor(Client $client, CarbonImmutable $date, CarbonImmutable $today): SessionStatus
    {
        if ($date->isSameDay($today)) {
            return self::TODAY_STATUS[$client->name] ?? SessionStatus::Scheduled;
        }

        if ($date->gt($today)) {
            return SessionStatus::Scheduled;
        }

        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 5 => SessionStatus::NoShowUnexcused,
            $roll <= 11 => SessionStatus::NoShowExcused,
            $roll <= 15 => SessionStatus::Canceled,
            default => SessionStatus::Completed,
        };
    }

    /**
     * Cobranças dos ciclos já fechados, seguindo o ciclo de pagamento do cliente.
     */
    protected function seedCharges(Client $client, Collection $sessions, CarbonImmutable $today, CarbonImmutable $from): void
    {
        $dueDates = $this->dueDates($client, $today, $from);
        $periodStart = $from;

        foreach ($dueDates as $position => $due) {
            $billable = $sessions->filter(fn (ClientSession $session) => $session->status->isBillable()
                && $session->scheduled_at->between($periodStart, $due->endOfDay()));
            $amount = (float) $billable->sum('value');
            $sentAt = $due->setTime(9, 0);

            if ($amount > 0 && $sentAt->lte(now())) {
                $isLast = $position === array_key_last($dueDates);
                $daysAgo = (int) $sentAt->diffInDays($today);
                // Ciclos antigos já foram pagos; dos fechados nesta semana, parte segue pendente.
                $unpaid = in_array($client->name, self::UNPAID, true);
                $paid = ! $isLast || (! $unpaid && ($daysAgo > 7 || ($daysAgo >= 2 && mt_rand(1, 100) <= 65)));

                (new Charge)->forceFill([
                    'user_id' => $client->user_id,
                    'client_id' => $client->id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $due->toDateString(),
                    'amount' => $amount,
                    'status' => $paid ? ChargeStatus::Paid : ChargeStatus::Pending,
                    'channel' => $client->billing_channel,
                    'sent_at' => $sentAt,
                    'paid_at' => $paid ? $sentAt->addDays(mt_rand(1, min(5, max(1, $daysAgo)))) : null,
                ] + $this->emailSent($client, $sentAt))->save();
            }

            $client->forceFill(['last_charged_at' => $sentAt])->save();
            $periodStart = $due->addDay();
        }
    }

    /**
     * Datas de fechamento do ciclo entre o início da massa e hoje.
     *
     * @return array<int, CarbonImmutable>
     */
    protected function dueDates(Client $client, CarbonImmutable $today, CarbonImmutable $from): array
    {
        $dates = [];

        if ($client->payment_cycle === PaymentCycle::Monthly) {
            for ($month = $from; $month->lte($today); $month = $month->addMonthNoOverflow()) {
                $due = $month->setDay(min($client->payment_day, $month->daysInMonth));
                if ($due->gt($from) && $due->lte($today)) {
                    $dates[] = $due;
                }
            }

            return $dates;
        }

        $step = $client->payment_cycle === PaymentCycle::Weekly ? 7 : max(1, (int) $client->payment_interval_days);
        for ($due = $from->addDays($step - 1); $due->lte($today); $due = $due->addDays($step)) {
            $dates[] = $due;
        }

        return $dates;
    }

    /**
     * Uma cobrança avulsa pendente, disparada a partir de uma sessão.
     */
    protected function seedSessionCharge(User $user, CarbonImmutable $today): void
    {
        $session = ClientSession::query()
            ->whereHas('client', fn ($query) => $query->where('user_id', $user->id)->where('name', 'Carlos Mendes'))
            ->where('status', SessionStatus::Completed)
            ->where('scheduled_at', '<', $today->subDays(5))
            ->latest('scheduled_at')
            ->first();

        if (! $session) {
            return;
        }

        (new Charge)->forceFill([
            'user_id' => $user->id,
            'client_id' => $session->client_id,
            'client_session_id' => $session->id,
            'period_start' => $session->scheduled_at->toDateString(),
            'period_end' => $session->scheduled_at->toDateString(),
            'amount' => (float) $session->value,
            'status' => ChargeStatus::Pending,
            'channel' => $session->client->billing_channel,
            'sent_at' => $session->scheduled_at->addHours(2),
        ] + $this->emailSent($session->client, $session->scheduled_at->addHours(2)))->save();
    }

    /**
     * Cobranças por e-mail da demonstração aparecem com o e-mail já enviado
     * (situação "Enviado" e contador de e-mails em Cobranças).
     *
     * @return array<string, mixed>
     */
    protected function emailSent(Client $client, CarbonInterface $sentAt): array
    {
        if (! $client->billing_channel->sendsEmail()) {
            return [];
        }

        return [
            'emails_sent' => 1,
            'email_queued_at' => $sentAt,
            'email_sent_at' => $sentAt->copy()->addMinute(),
        ];
    }
}
