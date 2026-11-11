<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Acompanhamento do e-mail de cobrança: quando entrou na fila, quando
     * saiu de fato (evento MessageSent) e se falhou. Cobranças anteriores
     * ficam sem registro (null), porque não há como saber se o e-mail saiu.
     */
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->unsignedSmallInteger('emails_sent')->default(0)->after('sent_at')
                ->comment('E-mails de cobrança efetivamente enviados (inclui reenvios)');
            $table->timestamp('email_queued_at')->nullable()->after('emails_sent')
                ->comment('Último e-mail de cobrança colocado na fila');
            $table->timestamp('email_sent_at')->nullable()->after('email_queued_at')
                ->comment('Último e-mail de cobrança entregue ao servidor de e-mail');
            $table->timestamp('email_failed_at')->nullable()->after('email_sent_at')
                ->comment('Última falha no envio do e-mail de cobrança');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropColumn(['emails_sent', 'email_queued_at', 'email_sent_at', 'email_failed_at']);
        });
    }
};
