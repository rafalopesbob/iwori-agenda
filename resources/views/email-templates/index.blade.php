@extends('layouts.app')

@section('title', 'Templates de e-mail — Agenda Iwori')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold text-mvindigo">Templates de e-mail</h1>
</div>

@if (session('status'))
    <div class="bg-mvteal-light border border-mvteal/30 text-mvteal-dark rounded-lg px-4 py-3 mb-6">
        {{ session('status') }}
    </div>
@endif

<p class="text-sm text-gray-600 mb-6">
    Personalize os e-mails enviados aos seus clientes. Enquanto um tipo não tiver template
    personalizado, o padrão do sistema é usado.
</p>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    @foreach ($types as $type)
        @php($template = $templates->get($type->value))
        <div class="bg-white rounded-2xl shadow p-6 flex flex-col gap-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="font-semibold text-mvindigo">{{ $type->label() }}</h2>
                    @if ($template)
                        <p class="text-sm text-gray-600 mt-1">{{ $template->name }}</p>
                        <p class="text-xs text-gray-400 mt-1">Atualizado em {{ $template->updated_at->format('d/m/Y H:i') }}</p>
                    @else
                        <p class="text-sm text-gray-500 mt-1">Padrão do sistema</p>
                    @endif
                </div>
                <span class="text-xs font-medium px-2.5 py-1 rounded-full shrink-0
                             {{ $template ? 'bg-mvteal-light text-mvteal-dark' : 'bg-gray-100 text-gray-500' }}">
                    {{ $template ? 'Personalizado' : 'Padrão' }}
                </span>
            </div>

            <div class="text-sm text-gray-500 bg-mvsand rounded-lg px-3 py-2 truncate">
                {{ $template->subject ?? $type->defaultSubject() }}
            </div>

            <div class="flex items-center gap-4 mt-auto pt-2">
                @if ($template)
                    <a href="{{ route('email-templates.edit', $template) }}"
                       class="text-mvteal-dark font-medium text-sm hover:underline">Editar</a>
                    <form method="POST" action="{{ route('email-templates.destroy', $template) }}"
                          onsubmit="return confirm('Remover o template? O padrão do sistema volta a ser usado.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 text-sm hover:underline">Voltar ao padrão</button>
                    </form>
                @else
                    <a href="{{ route('email-templates.create', ['type' => $type->value]) }}"
                       class="bg-mvteal hover:bg-mvteal-dark text-white text-sm font-medium px-4 py-2 rounded-lg">
                        Personalizar
                    </a>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endsection
