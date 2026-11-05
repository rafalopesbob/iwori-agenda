<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Celular brasileiro: DDD + 9 + oito dígitos, com o DDI 55 opcional.
 * Máscara e espaços são ignorados; só os dígitos contam.
 */
class BrazilianMobilePhone implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (strlen($digits) === 13 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        if (! preg_match('/^[1-9]{2}9\d{8}$/', $digits)) {
            $fail('Informe um celular com DDD, por exemplo (11) 98765-4321.');
        }
    }
}
