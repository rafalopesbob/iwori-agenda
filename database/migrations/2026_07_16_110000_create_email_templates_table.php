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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->comment('Profissional dono do template')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('type', 30)->comment('Tipo do template (EmailTemplateType)');
            $table->string('name', 100)->comment('Nome de exibição do template');
            $table->string('subject', 150)->comment('Assunto do e-mail, com variáveis {{...}}');
            $table->text('body')->comment('Corpo do e-mail em texto simples, com variáveis {{...}}');
            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
