<?php

namespace App\Models;

use App\Enums\EmailTemplateType;
use App\Enums\MessageChannel;
use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Texto personalizado de uma mensagem ao cliente, por e-mail ou WhatsApp
// (o nome da tabela vem de quando só havia e-mail).
// user_id fica fora do Fillable de propósito: o vínculo com o profissional
// deve ser feito via relacionamento ($user->emailTemplates()->create(...)).
#[Fillable(['channel', 'type', 'name', 'subject', 'body'])]
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasFactory;

    /**
     * Sem canal informado, o texto é de e-mail (como na coluna do banco).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'channel' => 'email',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'type' => EmailTemplateType::class,
        ];
    }

    /**
     * Profissional dono do template.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
