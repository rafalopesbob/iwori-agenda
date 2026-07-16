<?php

namespace App\Models;

use App\Enums\EmailTemplateType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// user_id fica fora do Fillable de propósito: o vínculo com o profissional
// deve ser feito via relacionamento ($user->emailTemplates()->create(...)).
#[Fillable(['type', 'name', 'subject', 'body'])]
class EmailTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\EmailTemplateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
