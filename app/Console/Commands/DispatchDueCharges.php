<?php

namespace App\Console\Commands;

use App\Jobs\SendClientCharge;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DispatchDueCharges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'charges:dispatch {--date= : Data de referência (Y-m-d), padrão hoje}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Despacha as cobranças dos clientes com pagamento vencendo na data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $this->option('date'))
            : CarbonImmutable::today();

        $due = Client::query()
            ->active()
            ->get()
            ->filter(fn (Client $client) => $client->isChargeDueOn($date));

        $due->each(fn (Client $client) => SendClientCharge::dispatch($client));

        $this->info("Cobranças despachadas para {$date->format('d/m/Y')}: {$due->count()}");

        return self::SUCCESS;
    }
}
