@extends('layouts.app')

@section('title', 'Pré-visualização — Agenda Iwori')

@section('content')
<div class="max-w-2xl mx-auto space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">Pré-visualização</h1>
        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold bg-warn-tint text-warn-ink">
            Dados de exemplo
        </span>
    </div>

    <p class="text-sm text-muted">{{ $type->label() }}</p>

    <div class="bg-white rounded-2xl border border-line overflow-hidden">
        <div class="border-b border-line px-6 py-4">
            <p class="text-xs font-medium text-muted uppercase tracking-wide mb-1">Assunto</p>
            <p class="font-medium text-ink">{{ $subject }}</p>
        </div>
        <div class="px-6 py-6">
            <p class="text-xs font-medium text-muted uppercase tracking-wide mb-3">Corpo</p>
            <div class="text-ink leading-relaxed whitespace-pre-line">{{ $body }}</div>

            <div class="mt-6 rounded-xl border border-dashed border-line-strong bg-canvas px-4 py-3 text-sm text-muted">
                Os detalhes automáticos (tabela de sessões, totais ou dados da sessão)
                aparecem aqui no e-mail real.
            </div>
        </div>
    </div>
</div>
@endsection
