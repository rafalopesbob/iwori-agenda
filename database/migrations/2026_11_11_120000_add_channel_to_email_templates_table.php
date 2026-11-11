<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A mesma tabela guarda os textos de e-mail e de WhatsApp de cada
     * profissional: um por tipo de mensagem e canal.
     */
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->string('channel', 20)->default('email')->after('user_id')
                ->comment('Canal do texto (MessageChannel): email ou whatsapp');
            // WhatsApp não tem assunto.
            $table->string('subject', 150)->nullable()->change();
        });

        Schema::table('email_templates', function (Blueprint $table) {
            // O índice novo começa por user_id e passa a atender a chave
            // estrangeira; só depois o antigo pode sair (exigência do MySQL).
            $table->unique(['user_id', 'type', 'channel']);
            $table->dropUnique(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('email_templates')->where('channel', '!=', 'email')->delete();
        DB::table('email_templates')->whereNull('subject')->update(['subject' => '']);

        Schema::table('email_templates', function (Blueprint $table) {
            $table->unique(['user_id', 'type']);
            $table->dropUnique(['user_id', 'type', 'channel']);
        });

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropColumn('channel');
            $table->string('subject', 150)->nullable(false)->change();
        });
    }
};
