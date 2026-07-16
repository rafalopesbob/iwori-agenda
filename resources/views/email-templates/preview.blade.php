@extends('layouts.app')

@section('title', 'Pré-visualização — Agenda Iwori')

@section('content')
<div class="max-w-2xl mx-auto space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-mvindigo">Pré-visualização</h1>
        <span class="text-xs font-medium px-2.5 py-1 rounded-full bg-mvrose-light text-mvrose-dark">
            Dados de exemplo
        </span>
    </div>

    <p class="text-sm text-gray-600">{{ $type->label() }}</p>

    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Assunto</p>
            <p class="font-medium text-gray-800">{{ $subject }}</p>
        </div>
        <div class="px-6 py-6">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-3">Corpo</p>
            <div class="text-gray-800 leading-relaxed whitespace-pre-line">{{ $body }}</div>

            <div class="mt-6 border border-dashed border-gray-200 rounded-lg px-4 py-3 text-sm text-gray-400">
                Os detalhes automáticos (tabela de sessões, totais ou dados da sessão)
                aparecem aqui no e-mail real.
            </div>
        </div>
    </div>
</div>
@endsection
