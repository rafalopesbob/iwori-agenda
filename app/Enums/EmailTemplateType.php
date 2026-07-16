<?php

namespace App\Enums;

enum EmailTemplateType: string
{
    case Charge = 'charge';
    case SessionScheduled = 'session_scheduled';

    /**
     * Rótulo em português para exibição nas telas.
     */
    public function label(): string
    {
        return match ($this) {
            self::Charge => 'Cobrança de período',
            self::SessionScheduled => 'Confirmação de agendamento',
        };
    }

    /**
     * Variáveis disponíveis para o template, com descrição em português.
     *
     * @return array<string, string>
     */
    public function variables(): array
    {
        return match ($this) {
            self::Charge => [
                'cliente_nome' => 'Nome do cliente',
                'profissional_nome' => 'Seu nome',
                'valor' => 'Valor total do período (ex.: R$ 200,00)',
                'periodo_inicio' => 'Início do período (dd/mm/aaaa)',
                'periodo_fim' => 'Fim do período (dd/mm/aaaa)',
                'periodo' => 'Período completo (ex.: 01/07/2026 a 31/07/2026)',
                'lista_sessoes' => 'Lista das sessões do período, uma por linha',
            ],
            self::SessionScheduled => [
                'cliente_nome' => 'Nome do cliente',
                'profissional_nome' => 'Seu nome',
                'data' => 'Data da sessão (dd/mm/aaaa)',
                'hora' => 'Horário da sessão (hh:mm)',
                'duracao' => 'Duração em minutos',
            ],
        };
    }

    /**
     * Assunto padrão do sistema.
     */
    public function defaultSubject(): string
    {
        return match ($this) {
            self::Charge => 'Valor a pagar: {{valor}} — período {{periodo}}',
            self::SessionScheduled => 'Sessão confirmada para {{data}} às {{hora}}',
        };
    }

    /**
     * Corpo padrão do sistema (texto de abertura do e-mail;
     * tabela de sessões/painel de detalhes são fixos na view).
     */
    public function defaultBody(): string
    {
        return match ($this) {
            self::Charge => "Olá, {{cliente_nome}}!\n\n"
                ."Aqui está o resumo das suas sessões com {{profissional_nome}} "
                ."no período de {{periodo_inicio}} a {{periodo_fim}}:",
            self::SessionScheduled => "Olá, {{cliente_nome}}!\n\n"
                .'Sua sessão com {{profissional_nome}} está agendada:',
        };
    }

    /**
     * Dados fictícios para pré-visualização do template.
     *
     * @return array<string, string>
     */
    public function sampleData(): array
    {
        return match ($this) {
            self::Charge => [
                'cliente_nome' => 'Ana Beatriz',
                'profissional_nome' => 'Você',
                'valor' => 'R$ 480,00',
                'periodo_inicio' => '01/07/2026',
                'periodo_fim' => '31/07/2026',
                'periodo' => '01/07/2026 a 31/07/2026',
                'lista_sessoes' => "02/07/2026 14:00 — Realizada — R$ 120,00\n"
                    ."09/07/2026 14:00 — Realizada — R$ 120,00\n"
                    ."16/07/2026 14:00 — Realizada — R$ 120,00\n"
                    .'23/07/2026 14:00 — Realizada — R$ 120,00',
            ],
            self::SessionScheduled => [
                'cliente_nome' => 'Ana Beatriz',
                'profissional_nome' => 'Você',
                'data' => '23/07/2026',
                'hora' => '14:00',
                'duracao' => '50',
            ],
        };
    }
}
