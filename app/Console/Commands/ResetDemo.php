<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class ResetDemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recria a conta de demonstração com dados coerentes com a data de hoje';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->laravel->isProduction() && ! config('demo.enabled')) {
            $this->error('demo:reset só roda em produção num ambiente de demonstração (DEMO_MODE=true).');

            return self::FAILURE;
        }

        $this->laravel->make(DemoSeeder::class)
            ->setContainer($this->laravel)
            ->setCommand($this)
            ->__invoke();

        $this->info('Conta de demonstração recriada: '.DemoSeeder::EMAIL);

        return self::SUCCESS;
    }
}
