<?php

namespace App\Models;

use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'google_access_token', 'google_refresh_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Envia o link de confirmação de e-mail pela fila.
     *
     * Enquanto o e-mail não for confirmado, a conta usa o app normalmente, mas
     * nenhum e-mail ou WhatsApp sai para os clientes (rotas com middleware
     * 'verified' e checagens em ClientSessionController e DispatchDueCharges).
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailQueued);
    }

    /**
     * Envia o link de "esqueci minha senha" pela fila.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordQueued($token));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'google_access_token' => 'encrypted',
            'google_refresh_token' => 'encrypted',
            'google_token_expires_at' => 'datetime',
        ];
    }

    /**
     * Indica se o profissional conectou o Google Calendar.
     */
    public function hasGoogleCalendar(): bool
    {
        return $this->google_refresh_token !== null;
    }

    /**
     * URL pública da foto de perfil, quando houver.
     */
    public function photoUrl(): ?string
    {
        return $this->photo_path !== null
            ? Storage::disk('public')->url($this->photo_path)
            : null;
    }

    /**
     * Clientes (pacientes/alunos) do profissional.
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * Templates de e-mail personalizados do profissional.
     */
    public function emailTemplates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }

    /**
     * Cobranças registradas do profissional.
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }
}
