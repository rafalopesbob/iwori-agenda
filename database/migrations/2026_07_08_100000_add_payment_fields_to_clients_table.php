<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->enum('payment_cycle', ['weekly', 'monthly', 'interval'])
                ->default('monthly')
                ->comment('Ciclo de cobrança do cliente')
                ->after('session_value');
            $table->unsignedTinyInteger('payment_day')
                ->nullable()
                ->comment('Dia do mês da cobrança (ciclo mensal)')
                ->after('payment_cycle');
            $table->unsignedSmallInteger('payment_interval_days')
                ->nullable()
                ->comment('Intervalo em dias (ciclo por intervalo)')
                ->after('payment_day');
            $table->enum('billing_channel', ['email', 'whatsapp', 'both'])
                ->default('email')
                ->comment('Canal de envio da cobrança')
                ->after('payment_interval_days');
            $table->timestamp('last_charged_at')
                ->nullable()
                ->comment('Última cobrança enviada')
                ->after('billing_channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'payment_cycle',
                'payment_day',
                'payment_interval_days',
                'billing_channel',
                'last_charged_at',
            ]);
        });
    }
};
