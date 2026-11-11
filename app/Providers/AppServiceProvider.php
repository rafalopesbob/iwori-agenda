<?php

namespace App\Providers;

use App\Services\ChargeEmailTracker;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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
        // Ambiente de demonstração: nada sai do sistema, mesmo que alguém configure
        // as variáveis de envio por engano. E-mails vão para o log; WhatsApp e
        // Google Calendar ficam desligados (os serviços checam isConfigured()).
        // Comprovantes enviados na demo ficam no disco do contêiner, que é
        // descartável, e nunca no armazenamento dos dados reais.
        if (config('demo.enabled')) {
            config([
                'mail.default' => 'log',
                'services.whatsapp.token' => null,
                'services.google.client_id' => null,
                'services.google.client_secret' => null,
                'filesystems.uploads' => 'local',
            ]);
        }

        // Garante que todas as URLs geradas usem HTTPS em produção.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');

            // Links absolutos (confirmação de e-mail, redefinição de senha) sempre
            // a partir do APP_URL, nunca do Host/X-Forwarded-Host da requisição,
            // que o cliente pode forjar.
            URL::forceRootUrl(config('app.url'));
        }

        // Política de senha padrão para qualquer validação com Password::defaults().
        Password::defaults(function () {
            $rule = Password::min(12)->letters()->mixedCase()->numbers()->symbols();

            // Em produção, também rejeita senhas presentes em vazamentos conhecidos.
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        // Limite para rotas de autenticação (aplicar com middleware 'throttle:auth').
        // O IP sozinho não basta: atrás de proxy ele vem do X-Forwarded-For e pode ser
        // forjado. Por isso também há um limite por e-mail, que trocar de IP não contorna.
        RateLimiter::for('auth', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('ip:'.$request->ip()),
                Limit::perMinute(5)->by('email:'.$email),
            ];
        });

        // Texto do e-mail de confirmação de conta.
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Confirme seu e-mail — '.config('app.name'))
                ->greeting('Olá, '.$notifiable->name.'!')
                ->line('Confirme seu e-mail para liberar o envio de confirmações de sessão e de cobranças aos seus clientes.')
                ->action('Confirmar e-mail', $url)
                ->line('O link vale por '.config('auth.verification.expire', 60).' minutos.')
                ->line('Se você não criou uma conta na '.config('app.name').', ignore este e-mail.')
                ->salutation('Equipe '.config('app.name'));
        });

        // Texto do e-mail de "esqueci minha senha".
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Redefinir senha — '.config('app.name'))
                ->greeting('Olá, '.$notifiable->name.'!')
                ->line('Recebemos um pedido para redefinir a senha da sua conta.')
                ->action('Criar nova senha', $url)
                ->line('O link vale por '.$expire.' minutos e só pode ser usado uma vez.')
                ->line('Se não foi você, ignore este e-mail: sua senha continua a mesma.')
                ->salutation('Equipe '.config('app.name'));
        });

        // Ações que disparam e-mail/WhatsApp para clientes (aplicar com 'throttle:messages').
        // Contido por usuário e por rota, para que uma conta não use a plataforma para
        // disparar mensagens em massa e esgotar as cotas de envio de todos.
        RateLimiter::for('messages', function (Request $request) {
            return Limit::perHour(60)->by($request->user()?->id.'|'.$request->route()?->getName());
        });

        // Marca na cobrança quando o e-mail dela sai de fato (contador e situação do envio).
        Event::listen(MessageSent::class, [ChargeEmailTracker::class, 'sent']);
    }
}
