<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ChargeController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoogleCalendarController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:auth');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:auth');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('clients', ClientController::class);

    Route::get('/sessions', [ClientSessionController::class, 'index'])->name('sessions.index');
    Route::get('/sessions/create', [ClientSessionController::class, 'create'])->name('sessions.create');
    Route::post('/sessions', [ClientSessionController::class, 'store'])->name('sessions.store');
    Route::get('/sessions/{session}/edit', [ClientSessionController::class, 'edit'])->name('sessions.edit');
    Route::put('/sessions/{session}', [ClientSessionController::class, 'update'])->name('sessions.update');
    Route::patch('/sessions/{session}/status', [ClientSessionController::class, 'updateStatus'])->name('sessions.status');
    Route::patch('/sessions/{session}/move', [ClientSessionController::class, 'move'])->name('sessions.move');
    Route::post('/sessions/{session}/charge', [ChargeController::class, 'session'])->name('sessions.charge');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/{client}/charge', [ChargeController::class, 'client'])->name('billing.charge');

    Route::get('/google/connect', [GoogleCalendarController::class, 'redirect'])->name('google.connect');
    Route::get('/google/callback', [GoogleCalendarController::class, 'callback'])->name('google.callback');
    Route::post('/google/disconnect', [GoogleCalendarController::class, 'disconnect'])->name('google.disconnect');
});
