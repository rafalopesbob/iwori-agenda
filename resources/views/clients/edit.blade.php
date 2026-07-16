@extends('layouts.app')

@section('title', 'Editar cliente — Agenda Iwori')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-2xl shadow p-8">
    <h1 class="text-2xl font-semibold text-mvindigo mb-6">Editar cliente</h1>

    <form method="POST" action="{{ route('clients.update', $client) }}" class="space-y-4">
        @method('PUT')
        @include('clients._form')

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-mvteal hover:bg-mvteal-dark text-white font-medium px-5 py-2.5 rounded-lg">
                Salvar alterações
            </button>
            <a href="{{ route('clients.index') }}" class="text-gray-600 hover:underline">Cancelar</a>
        </div>
    </form>
</div>
@endsection
