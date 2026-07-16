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
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->comment('Profissional dono da cobrança')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('client_id')
                ->comment('Cliente cobrado')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('client_session_id')
                ->nullable()
                ->comment('Sessão, quando cobrança avulsa')
                ->constrained()
                ->nullOnDelete();
            $table->date('period_start')->comment('Início do período coberto');
            $table->date('period_end')->comment('Fim do período coberto');
            $table->decimal('amount', 10, 2)->comment('Valor cobrado');
            $table->string('status', 20)->default('pending')->comment('Situação (ChargeStatus)');
            $table->string('channel', 20)->comment('Canal usado no disparo (BillingChannel)');
            $table->timestamp('sent_at')->nullable()->comment('Quando a cobrança foi enviada');
            $table->timestamp('paid_at')->nullable()->comment('Quando o pagamento foi confirmado');
            $table->string('receipt_path')->nullable()->comment('Comprovante no disco privado');
            $table->text('notes')->nullable()->comment('Observações do pagamento (criptografado)');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['client_id', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
