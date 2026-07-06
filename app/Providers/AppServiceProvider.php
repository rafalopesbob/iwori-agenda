<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Garante que todas as URLs geradas usem HTTPS em produção.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Política de senha padrão para qualquer validação com Password::defaults().
        Password::defaults(function () {
            $rule = Password::min(12)->letters()->mixedCase()->numbers()->symbols();

            // Em produção, também rejeita senhas presentes em vazamentos conhecidos.
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        // Limite para rotas de autenticação (aplicar com middleware 'throttle:auth').
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
