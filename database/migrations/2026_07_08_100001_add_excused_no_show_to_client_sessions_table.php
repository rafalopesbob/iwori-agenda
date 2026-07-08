<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adiciona o status "falta informada" (abonada). O no_show existente
     * passa a significar "falta não informada" (cobrada).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE client_sessions MODIFY COLUMN status ENUM('scheduled', 'completed', 'no_show', 'no_show_excused', 'canceled') NOT NULL DEFAULT 'scheduled'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('client_sessions')->where('status', 'no_show_excused')->update(['status' => 'no_show']);

        DB::statement("ALTER TABLE client_sessions MODIFY COLUMN status ENUM('scheduled', 'completed', 'no_show', 'canceled') NOT NULL DEFAULT 'scheduled'");
    }
};
