<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ChargeController;
use App\Http\Controllers\ChargeRecordController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoLoginController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\GoogleCalendarController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WhatsAppMessageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    // No ambiente de demonstração ninguém cria conta: todos usam a conta demo.
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register')
        ->middleware('not-demo');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware(['not-demo', 'throttle:auth']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:auth');

    // Login com conta Google (o /google/callback é da integração com o Google Agenda).
    // Desligado na demo, porque cria contas. O limite é por IP: o 'throttle:auth' usa
    // o e-mail do formulário, que aqui não existe.
    Route::middleware(['not-demo', 'throttle:20,1'])->group(function () {
        Route::get('/login/google', [GoogleLoginController::class, 'redirect'])->name('login.google');
        Route::get('/login/google/callback', [GoogleLoginController::class, 'callback'])->name('login.google.callback');
    });

    // Esqueci minha senha (desligado na demo: a conta é compartilhada e nada é enviado).
    Route::middleware('not-demo')->group(function () {
        Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email')
            ->middleware('throttle:auth');
        Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store')
            ->middleware('throttle:auth');
    });

    // Entrada com um clique na conta demo (só existe com DEMO_MODE=true).
    Route::post('/demo/entrar', DemoLoginController::class)->name('demo.login')
        ->middleware('throttle:20,1');
});

// auth.session invalida as outras sessões ativas quando a senha é trocada.
Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Confirmação de e-mail: sem ela, nada é enviado aos clientes (middleware 'verified').
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/photo', [ProfileController::class, 'photo'])->name('profile.photo.show');
    // A conta demo é compartilhada: ninguém troca e-mail, senha ou foto dela.
    Route::middleware('not-demo')->group(function () {
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo');
        Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
        Route::patch('/profile/reply-to', [ProfileController::class, 'updateReplyTo'])->name('profile.reply-to');
    });

    Route::resource('clients', ClientController::class);

    Route::get('/sessions', [ClientSessionController::class, 'index'])->name('sessions.index');
    Route::get('/sessions/create', [ClientSessionController::class, 'create'])->name('sessions.create');
    Route::post('/sessions', [ClientSessionController::class, 'store'])->name('sessions.store')
        ->middleware('throttle:messages');
    Route::get('/sessions/{session}/edit', [ClientSessionController::class, 'edit'])->name('sessions.edit');
    Route::put('/sessions/{session}', [ClientSessionController::class, 'update'])->name('sessions.update');
    Route::patch('/sessions/{session}/status', [ClientSessionController::class, 'updateStatus'])->name('sessions.status');
    Route::patch('/sessions/{session}/move', [ClientSessionController::class, 'move'])->name('sessions.move');
    Route::post('/sessions/{session}/charge', [ChargeController::class, 'session'])->name('sessions.charge')
        ->middleware(['verified', 'throttle:messages']);
    Route::post('/sessions/{session}/cancel-recurrence', [ClientSessionController::class, 'cancelRecurrence'])->name('sessions.recurrence.cancel');

    Route::post('/email-templates/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');
    Route::resource('email-templates', EmailTemplateController::class)
        ->except(['show'])
        ->parameters(['email-templates' => 'email_template']);

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/{client}/charge', [ChargeController::class, 'client'])->name('billing.charge')
        ->middleware(['verified', 'throttle:messages']);

    Route::get('/charges', [ChargeRecordController::class, 'index'])->name('charges.index');
    Route::patch('/charges/{charge}/pay', [ChargeRecordController::class, 'pay'])->name('charges.pay');
    Route::patch('/charges/{charge}/reopen', [ChargeRecordController::class, 'reopen'])->name('charges.reopen');
    Route::get('/charges/{charge}/receipt', [ChargeRecordController::class, 'receipt'])->name('charges.receipt');

    // WhatsApp do próprio profissional (link wa.me): o sistema só monta o
    // texto e abre a conversa; quem envia é ele. Por isso não exige 'verified'.
    Route::post('/billing/{client}/whatsapp', [WhatsAppMessageController::class, 'chargeClient'])->name('billing.whatsapp')
        ->middleware('throttle:messages');
    Route::post('/sessions/{session}/whatsapp-charge', [WhatsAppMessageController::class, 'chargeSession'])->name('sessions.whatsapp.charge')
        ->middleware('throttle:messages');
    Route::get('/sessions/{session}/whatsapp', [WhatsAppMessageController::class, 'session'])->name('sessions.whatsapp');
    Route::get('/charges/{charge}/whatsapp', [WhatsAppMessageController::class, 'charge'])->name('charges.whatsapp');

    Route::get('/google/connect', [GoogleCalendarController::class, 'redirect'])->name('google.connect');
    Route::get('/google/callback', [GoogleCalendarController::class, 'callback'])->name('google.callback');
    Route::post('/google/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('google.disconnect');
    Route::get('/ajuda/google-agenda', [GoogleCalendarController::class, 'help'])->name('help.google-calendar');
});
