@extends('layouts.app')

@section('title', 'Novo cliente — Agenda Iwori')

@section('content')
<div class="max-w-2xl mx-auto flex flex-wrap items-end justify-between gap-4 mb-8">
    <h1 class="text-3xl font-semibold tracking-tight text-ink">Novo cliente</h1>
</div>

<div class="max-w-2xl mx-auto bg-white rounded-2xl border border-line p-6 sm:p-8">
    <form method="POST" action="{{ route('clients.store') }}" class="space-y-5">
        @include('clients._form')

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit"
                    class="inline-flex items-center justify-center gap-2 min-h-11 px-4 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors cursor-pointer">
                Cadastrar
            </button>
            <a href="{{ route('clients.index') }}"
               class="inline-flex items-center justify-center gap-2 min-h-11 px-4 rounded-xl border border-line-strong bg-white text-ink hover:bg-canvas text-sm font-semibold transition-colors">Cancelar</a>
        </div>
    </form>
</div>
@endsection
