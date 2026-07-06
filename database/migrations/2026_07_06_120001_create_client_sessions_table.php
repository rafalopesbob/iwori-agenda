<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A tabela chama-se client_sessions porque "sessions" já é usada
     * pelo driver de sessão do framework (SESSION_DRIVER=database).
     */
    public function up(): void
    {
        Schema::create('client_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(50);
            $table->enum('status', ['scheduled', 'completed', 'no_show', 'canceled'])->default('scheduled');
            $table->decimal('value', 10, 2)->comment('Valor congelado no momento do agendamento');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'scheduled_at']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_sessions');
    }
};
