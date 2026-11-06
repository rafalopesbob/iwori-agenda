@extends('layouts.app')

@section('title', 'Nova senha — Agenda Iwori')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-2xl border border-line p-6 sm:p-8 mt-8">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Criar nova senha</h1>
    <p class="text-sm text-muted mt-1">Use pelo menos 12 caracteres, com letras maiúsculas e minúsculas, números e símbolos.</p>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5 mt-6">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="block text-sm font-medium text-ink mb-1.5">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username"
                   class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
            @error('email')
                <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-ink mb-1.5">Nova senha</label>
            <x-password-input id="password" name="password" required autofocus autocomplete="new-password" />
            @error('password')
                <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-ink mb-1.5">Confirmar nova senha</label>
            <x-password-input id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
        </div>

        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 min-h-11 px-5 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors">
            Salvar nova senha
        </button>
    </form>
</div>
@endsection
