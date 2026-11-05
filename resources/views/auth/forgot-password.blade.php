@extends('layouts.app')

@section('title', 'Esqueci minha senha — Agenda Iwori')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-2xl border border-line p-6 sm:p-8 mt-8">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Esqueci minha senha</h1>
    <p class="text-sm text-muted mt-1">Informe o e-mail da sua conta e enviaremos um link para criar uma nova senha.</p>

    @if (session('status'))
        <div role="status" class="mt-5 rounded-xl border border-accent/20 bg-accent-tint px-4 py-3 text-sm font-medium text-accent-ink">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5 mt-6">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-ink mb-1.5">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
            @error('email')
                <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 min-h-11 px-5 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors">
            Enviar link
        </button>
    </form>

    <p class="mt-4 flex flex-wrap items-center justify-center gap-x-1 text-sm text-muted">
        Lembrou a senha?
        <a href="{{ route('login') }}" class="inline-flex items-center min-h-11 font-medium text-brand hover:underline">Entrar</a>
    </p>
</div>
@endsection
