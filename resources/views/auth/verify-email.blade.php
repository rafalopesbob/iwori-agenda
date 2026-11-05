@extends('layouts.app')

@section('title', 'Confirme seu e-mail — Agenda Iwori')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-2xl border border-line p-6 sm:p-8 mt-8">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Confirme seu e-mail</h1>
    <p class="text-sm text-muted mt-2">
        Enviamos um link de confirmação para <span class="font-medium text-ink">{{ $user->email }}</span>.
        Até você confirmar, sua agenda funciona normalmente, mas nenhuma confirmação de sessão ou cobrança é enviada aos seus clientes.
    </p>

    @if (session('status'))
        <div role="status" class="mt-5 rounded-xl border border-accent/20 bg-accent-tint px-4 py-3 text-sm font-medium text-accent-ink">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
        @csrf
        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 min-h-11 px-5 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors">
            Reenviar link de confirmação
        </button>
    </form>

    <p class="mt-4 flex flex-wrap items-center justify-center gap-x-1 text-sm text-muted">
        E-mail errado?
        <a href="{{ route('profile.edit') }}" class="inline-flex items-center min-h-11 font-medium text-brand hover:underline">Corrigir no perfil</a>
        <span aria-hidden="true">·</span>
        <a href="{{ route('dashboard') }}" class="inline-flex items-center min-h-11 font-medium text-brand hover:underline">Voltar ao painel</a>
    </p>
</div>
@endsection
