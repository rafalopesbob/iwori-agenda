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
        Schema::table('client_sessions', function (Blueprint $table) {
            $table->ulid('recurrence_group_id')
                ->nullable()
                ->comment('Agrupa as ocorrências geradas por uma sessão recorrente')
                ->after('google_event_id');

            $table->index('recurrence_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_sessions', function (Blueprint $table) {
            $table->dropIndex(['recurrence_group_id']);
            $table->dropColumn('recurrence_group_id');
        });
    }
};
