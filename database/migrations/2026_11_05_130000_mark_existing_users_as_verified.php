<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A confirmação de e-mail passou a ser exigida para enviar mensagens aos
     * clientes. As contas que já existiam antes disso foram criadas sem esse
     * passo e são de profissionais reais: ficam como confirmadas para não
     * interromper as cobranças delas. Contas novas precisam confirmar.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    /**
     * Reverse the migrations.
     *
     * Não há como saber quais contas foram marcadas aqui; desfazer não altera nada.
     */
    public function down(): void
    {
        //
    }
};
