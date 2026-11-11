<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * WhatsApp de cada profissional (WhatsApp Cloud API da Meta): o envio
     * automático sai do número dele, nunca de um número único do sistema.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_phone_number_id', 40)->nullable()->after('reply_to_email')
                ->comment('ID do número na Meta (Phone number ID)');
            $table->string('whatsapp_business_account_id', 40)->nullable()->after('whatsapp_phone_number_id')
                ->comment('ID da conta do WhatsApp Business na Meta (WABA ID)');
            $table->text('whatsapp_token')->nullable()->after('whatsapp_business_account_id')
                ->comment('Token de acesso da Meta, criptografado');
            $table->string('whatsapp_display_phone', 40)->nullable()->after('whatsapp_token')
                ->comment('Número como a Meta mostra, conferido ao conectar');
            $table->string('whatsapp_verified_name')->nullable()->after('whatsapp_display_phone')
                ->comment('Nome exibido do número na Meta');
            $table->string('whatsapp_template', 100)->nullable()->after('whatsapp_verified_name')
                ->comment('Modelo aprovado usado na cobrança (vazio: cobranca_iwori)');
            $table->timestamp('whatsapp_connected_at')->nullable()->after('whatsapp_template');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_phone_number_id',
                'whatsapp_business_account_id',
                'whatsapp_token',
                'whatsapp_display_phone',
                'whatsapp_verified_name',
                'whatsapp_template',
                'whatsapp_connected_at',
            ]);
        });
    }
};
