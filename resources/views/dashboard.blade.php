@extends('layouts.app')

@section('title', 'Painel — Iwori Agenda')

@section('content')
<div class="bg-white rounded-2xl shadow p-8">
    <h1 class="text-2xl font-semibold text-emerald-800">Bem-vindo(a), {{ auth()->user()->name }}!</h1>
    <p class="text-gray-600 mt-2">
        Este é o seu painel. Em breve: calendário de sessões, gestão de clientes e faturamento do ciclo mensal.
    </p>
</div>
@endsection
