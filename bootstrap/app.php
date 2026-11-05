<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Cobranças dos ciclos de pagamento que vencem no dia, em horário
        // comercial de Brasília (o app roda em UTC).
        $schedule->command('charges:dispatch')->dailyAt('09:00')->timezone('America/Sao_Paulo');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Na nuvem (ex.: Render) o app fica atrás de um load balancer: confiar nos
        // headers X-Forwarded-* dá o IP real ao throttle de login e detecta o HTTPS.
        $middleware->trustProxies(at: '*');

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
