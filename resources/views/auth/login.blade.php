@extends('layouts.app')

@section('title', 'Entrar — Agenda Iwori')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-2xl border border-line p-6 sm:p-8 mt-8">
    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 28 28" class="mb-5" aria-hidden="true">
        <path d="M2 26V14a12 12 0 0 1 24 0v12Z" fill="#3c3a68"/>
        <path d="M7 26V15a7 7 0 0 1 14 0v11Z" fill="#0b7a77"/>
        <path d="M11 26v-9a3 3 0 0 1 6 0v9Z" fill="#e7a3b8"/>
    </svg>
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Entrar</h1>
    <p class="text-sm text-muted mt-1">Acesse sua agenda.</p>

    <form method="POST" action="{{ route('login') }}" class="space-y-5 mt-6">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-ink mb-1.5">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
            @error('email')
                <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-ink mb-1.5">Senha</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
            @error('password')
                <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2.5 min-h-11 text-sm text-ink cursor-pointer">
            <input type="checkbox" name="remember" class="size-4 rounded border-line-strong accent-brand">
            Manter conectado
        </label>

        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 min-h-11 px-5 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors">
            Entrar
        </button>
    </form>

    <p class="mt-4 flex flex-wrap items-center justify-center gap-x-1 text-sm text-muted">
        Ainda não tem conta?
        <a href="{{ route('register') }}" class="inline-flex items-center min-h-11 font-medium text-brand hover:underline">Criar conta</a>
    </p>
</div>
@endsection
