<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'google_access_token', 'google_refresh_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
