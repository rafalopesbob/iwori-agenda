<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientSessionController;
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

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::resource('clients', ClientController::class);

    Route::get('/sessions', [ClientSessionController::class, 'index'])->name('sessions.index');
    Route::get('/sessions/create', [ClientSessionController::class, 'create'])->name('sessions.create');
    Route::post('/sessions', [ClientSessionController::class, 'store'])->name('sessions.store');
    Route::patch('/sessions/{session}/status', [ClientSessionController::class, 'updateStatus'])->name('sessions.status');
});
