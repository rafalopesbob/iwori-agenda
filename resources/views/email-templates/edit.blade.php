@extends('layouts.app')

@section('title', 'Editar template — Agenda Iwori')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-2xl border border-line p-6 sm:p-8"
     x-data="{
         insertVariable(name) {
             const el = this.$refs.body;
             const token = '{{ '{{' }}' + name + '{{ '}}' }}';
             const start = el.selectionStart ?? el.value.length;
             el.value = el.value.slice(0, start) + token + el.value.slice(el.selectionEnd ?? start);
             el.focus();
             el.selectionStart = el.selectionEnd = start + token.length;
         }
     }">
    <h1 class="text-2xl font-semibold tracking-tight text-ink mb-1">Editar template</h1>
    <p class="text-sm text-muted mb-6">{{ $template->type->label() }}</p>

    @php($type = $template->type)

    <form method="POST" action="{{ route('email-templates.update', $template) }}" class="space-y-5">
        @method('PUT')
        @include('email-templates._form')

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="inline-flex items-center justify-center gap-2 min-h-11 px-5 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors">
                Salvar alterações
            </button>
            <button type="submit" formaction="{{ route('email-templates.preview') }}" formtarget="_blank" formmethod="post"
                    name="type" value="{{ $type->value }}"
                    class="inline-flex items-center justify-center gap-2 min-h-11 px-4 rounded-xl border border-line-strong bg-white text-ink hover:bg-canvas text-sm font-semibold transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-4 shrink-0" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                </svg>
                Pré-visualizar
            </button>
            <a href="{{ route('email-templates.index') }}" class="inline-flex items-center min-h-11 px-2 text-sm font-medium text-muted hover:text-ink">Cancelar</a>
        </div>
    </form>
</div>
@endsection
