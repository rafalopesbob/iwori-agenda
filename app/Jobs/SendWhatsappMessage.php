<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class SendWhatsappMessage implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Tentativas antes de desistir: falhas da Cloud API costumam ser passageiras.
     */
    public int $tries = 3;

    /**
     * Espera entre as tentativas, em segundos.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     *
     * Job próprio para que uma falha na Meta seja refeita sozinha, sem
     * reenviar o e-mail disparado pela mesma cobrança. A mensagem sai do
     * WhatsApp do profissional ($user), nunca de um número do sistema.
     *
     * @param  array<int, string>  $params  Parâmetros do corpo, na ordem do modelo.
     */
    public function __construct(
        public User $user,
        public string $to,
        public string $template,
        public array $params = [],
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsapp): void
    {
        $whatsapp->sendTemplate($this->user, $this->to, $this->template, $this->params);
    }
}
