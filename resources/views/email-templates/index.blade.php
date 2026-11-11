@extends('layouts.app')

@section('title', 'Mensagens — Agenda Iwori')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4 mb-8">
    <div>
        <h1 class="text-3xl font-semibold tracking-tight text-ink">Mensagens</h1>
        <p class="text-sm text-muted mt-1 max-w-2xl">
            Os textos que seus clientes recebem, por e-mail e pelo WhatsApp. Enquanto um texto não
            for personalizado, o padrão do sistema é usado.
        </p>
    </div>
</div>

@if (session('status'))
    <div class="rounded-xl border border-accent/20 bg-accent-tint px-4 py-3 text-sm font-medium text-accent-ink mb-6">
        {{ session('status') }}
    </div>
@endif

@foreach ($channels as $channel)
    <section id="{{ $channel->value }}" class="mb-10 scroll-mt-6">
        <h2 class="text-lg font-semibold tracking-tight text-ink">{{ $channel->label() }}</h2>
        <p class="text-sm text-muted mt-1 mb-4 max-w-2xl">
            @if ($channel === App\Enums\MessageChannel::Email)
                Saem do endereço do Agenda Iwori com o seu nome. As respostas dos clientes vão para o
                e-mail que você escolher em <a href="{{ route('profile.edit') }}#conexoes" class="font-medium text-brand hover:underline">Perfil → Conexões</a>.
            @else
                Os botões “WhatsApp” das cobranças e das sessões abrem o <strong class="font-semibold text-ink">seu</strong>
                WhatsApp com este texto pronto, na conversa com o cliente. Você confere e toca em enviar.
            @endif
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach ($types as $type)
                @php
                    $template = $templates->get($channel->value.'.'.$type->value);
                    $preview = $channel === App\Enums\MessageChannel::Email
                        ? ($template->subject ?? $type->defaultSubject())
                        : ($template->body ?? $type->defaultWhatsappBody());
                @endphp
                <div class="bg-white rounded-2xl border border-line p-6 flex flex-col gap-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-ink">{{ $type->label() }}</h3>
                            @if ($template)
                                <p class="text-sm text-muted mt-1">{{ $template->name }}</p>
                                <p class="text-[13px] text-muted mt-1">Atualizado em {{ $template->updated_at->format('d/m/Y H:i') }}</p>
                            @else
                                <p class="text-sm text-muted mt-1">Padrão do sistema</p>
                            @endif
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold shrink-0
                                     {{ $template ? 'bg-brand-tint text-brand' : 'bg-neutral-tint text-muted' }}">
                            {{ $template ? 'Personalizado' : 'Padrão' }}
                        </span>
                    </div>

                    <div class="rounded-xl border border-line bg-canvas px-3.5 py-2.5 text-sm text-muted truncate">
                        {{ $preview }}
                    </div>

                    <div class="flex flex-wrap items-center gap-3 mt-auto pt-2">
                        @if ($template)
                            <a href="{{ route('email-templates.edit', $template) }}"
                               class="inline-flex items-center justify-center gap-2 min-h-11 px-4 rounded-xl border border-line-strong bg-white text-ink hover:bg-canvas text-sm font-semibold transition-colors">Editar</a>
                            <form method="POST" action="{{ route('email-templates.destroy', $template) }}"
                                  onsubmit="return confirm('Remover o texto personalizado? O padrão do sistema volta a ser usado.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center min-h-11 px-2 text-sm font-medium text-danger-ink hover:underline">Voltar ao padrão</button>
                            </form>
                        @else
                            <a href="{{ route('email-templates.create', ['type' => $type->value, 'channel' => $channel->value]) }}"
                               class="inline-flex items-center justify-center gap-2 min-h-11 px-5 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors">
                                Personalizar
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endforeach
@endsection
