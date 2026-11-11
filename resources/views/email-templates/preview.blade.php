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

    <p class="text-sm text-muted">{{ $channel->label() }} · {{ $type->label() }}</p>

    @if ($channel === App\Enums\MessageChannel::Email)
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
    @else
        {{-- Aproximação de um balão do WhatsApp: *negrito* e _itálico_ como o app mostra. --}}
        @php
            $html = preg_replace(
                ['/\*([^*\n]+)\*/', '/(?<![\w])_([^_\n]+)_(?![\w])/'],
                ['<strong>$1</strong>', '<em>$1</em>'],
                e($body),
            );
        @endphp
        <div class="rounded-2xl border border-line bg-[#efeae2] p-4 sm:p-6">
            <div class="ml-auto max-w-[85%] rounded-xl rounded-tr-sm bg-[#d9fdd3] px-3.5 py-2.5 shadow-sm">
                <div class="text-[15px] text-[#111b21] leading-relaxed whitespace-pre-line break-words">{!! $html !!}</div>
            </div>
        </div>
        <p class="text-[13px] text-muted">
            É assim que a mensagem fica pronta no seu WhatsApp. Antes de enviar, você ainda pode mudar o texto.
        </p>
    @endif
</div>
@endsection
