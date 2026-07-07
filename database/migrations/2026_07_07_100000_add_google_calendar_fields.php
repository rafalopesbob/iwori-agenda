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
        Schema::table('users', function (Blueprint $table) {
            // Tokens OAuth do Google (armazenados criptografados via cast).
            $table->text('google_access_token')->nullable()->after('remember_token');
            $table->text('google_refresh_token')->nullable()->after('google_access_token');
            $table->timestamp('google_token_expires_at')->nullable()->after('google_refresh_token');
        });

        Schema::table('client_sessions', function (Blueprint $table) {
            // Evento espelhado no Google Calendar do profissional.
            $table->string('google_event_id')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_access_token', 'google_refresh_token', 'google_token_expires_at']);
        });

        Schema::table('client_sessions', function (Blueprint $table) {
            $table->dropColumn('google_event_id');
        });
    }
};
