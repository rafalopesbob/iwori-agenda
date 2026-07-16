@extends('layouts.app')

@section('title', 'Editar template — Agenda Iwori')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-2xl shadow p-8"
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
    <h1 class="text-2xl font-semibold text-mvindigo mb-1">Editar template</h1>
    <p class="text-sm text-gray-600 mb-6">{{ $template->type->label() }}</p>

    @php($type = $template->type)

    <form method="POST" action="{{ route('email-templates.update', $template) }}" class="space-y-4">
        @method('PUT')
        @include('email-templates._form')

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-mvteal hover:bg-mvteal-dark text-white font-medium px-5 py-2.5 rounded-lg">
                Salvar alterações
            </button>
            <button type="submit" formaction="{{ route('email-templates.preview') }}" formtarget="_blank" formmethod="post"
                    name="type" value="{{ $type->value }}"
                    class="border border-mvindigo/30 text-mvindigo font-medium px-5 py-2.5 rounded-lg hover:bg-mvsand">
                Pré-visualizar
            </button>
            <a href="{{ route('email-templates.index') }}" class="text-gray-600 hover:underline">Cancelar</a>
        </div>
    </form>
</div>
@endsection
