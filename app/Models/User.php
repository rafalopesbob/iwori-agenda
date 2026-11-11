<?php

namespace App\Models;

use App\Notifications\ResetPasswordQueued;
use App\Notifications\VerifyEmailQueued;
use App\Services\WhatsAppService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'google_id', 'google_access_token', 'google_refresh_token', 'whatsapp_token'])]
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
            'whatsapp_token' => 'encrypted',
            'whatsapp_connected_at' => 'datetime',
        ];
    }

    /**
     * Indica se o profissional conectou o WhatsApp dele (Cloud API da Meta).
     * O envio automático sai sempre do número do próprio profissional.
     */
    public function hasWhatsapp(): bool
    {
        return filled($this->whatsapp_phone_number_id) && filled($this->whatsapp_token);
    }

    /**
     * Nome do modelo aprovado na Meta usado nas cobranças por WhatsApp.
     */
    public function whatsappTemplate(): string
    {
        return $this->whatsapp_template ?: WhatsAppService::DEFAULT_TEMPLATE;
    }

    /**
     * Indica se a conta tem senha. Quem se cadastrou pelo Google entra sem
     * senha até definir uma no Perfil (ou por "Esqueci minha senha").
     */
    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    /**
     * Indica se o profissional conectou o Google Calendar.
     */
    public function hasGoogleCalendar(): bool
    {
        return $this->google_refresh_token !== null;
    }

    /**
     * Para onde vão as respostas dos clientes aos e-mails enviados pelo
     * sistema: o endereço escolhido no Perfil ou, sem ele, o e-mail de acesso.
     */
    public function replyToAddress(): string
    {
        return $this->reply_to_email ?: $this->email;
    }

    /**
     * URL da foto de perfil, quando houver. A foto é privada e sai pela rota
     * autenticada profile.photo.show; o ?v= muda a cada nova foto (cache).
     */
    public function photoUrl(): ?string
    {
        return $this->photo_path !== null
            ? route('profile.photo.show', ['v' => substr(sha1($this->photo_path), 0, 12)])
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
     * Textos personalizados das mensagens (e-mail e WhatsApp) do profissional.
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
