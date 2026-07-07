@extends('layouts.app')

@section('title', 'Iwori Agenda — Gestão de sessões, presenças e faturamento')

@section('content')
<section class="text-center py-12">
    <p class="text-5xl mb-4">🌿</p>
    <h1 class="text-4xl sm:text-5xl font-bold text-emerald-800 leading-tight">
        Sua agenda de sessões,<br class="hidden sm:block"> sem planilhas nem retrabalho
    </h1>
    <p class="text-lg text-gray-600 mt-4 max-w-2xl mx-auto">
        O Iwori Agenda organiza os atendimentos, controla presenças e fecha o faturamento
        do mês automaticamente — feito para terapeutas, instrutores e profissionais do
        desenvolvimento humano.
    </p>
    <div class="flex flex-wrap items-center justify-center gap-3 mt-8">
        @auth
            <a href="{{ route('sessions.index') }}"
               class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-xl font-medium text-lg">Ir para a agenda</a>
            <a href="{{ route('dashboard') }}"
               class="bg-white border border-emerald-200 text-emerald-700 hover:bg-emerald-50 px-6 py-3 rounded-xl font-medium text-lg">Meu painel</a>
        @else
            <a href="{{ route('register') }}"
               class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-xl font-medium text-lg">Começar agora</a>
            <a href="{{ route('login') }}"
               class="bg-white border border-emerald-200 text-emerald-700 hover:bg-emerald-50 px-6 py-3 rounded-xl font-medium text-lg">Entrar</a>
        @endauth
    </div>
</section>

<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pb-12">
    <div class="bg-white rounded-2xl shadow p-6">
        <p class="text-3xl">🗓️</p>
        <h2 class="font-semibold text-emerald-800 mt-3">Calendário dinâmico</h2>
        <p class="text-sm text-gray-600 mt-2">
            Visualize o mês inteiro e marque Realizado, Falta ou Cancelado com um clique, direto no calendário.
        </p>
    </div>
    <div class="bg-white rounded-2xl shadow p-6">
        <p class="text-3xl">👥</p>
        <h2 class="font-semibold text-emerald-800 mt-3">Gestão de clientes</h2>
        <p class="text-sm text-gray-600 mt-2">
            Perfis de pacientes e alunos com valor de contrato, contato e histórico de comparecimento.
        </p>
    </div>
    <div class="bg-white rounded-2xl shadow p-6">
        <p class="text-3xl">💰</p>
        <h2 class="font-semibold text-emerald-800 mt-3">Faturamento inteligente</h2>
        <p class="text-sm text-gray-600 mt-2">
            Fechamento do ciclo mensal automático, somando apenas as sessões em que houve comparecimento.
        </p>
    </div>
    <div class="bg-white rounded-2xl shadow p-6">
        <p class="text-3xl">🔒</p>
        <h2 class="font-semibold text-emerald-800 mt-3">Dados protegidos</h2>
        <p class="text-sm text-gray-600 mt-2">
            Anotações clínicas criptografadas e acesso isolado por profissional — cada um enxerga apenas os próprios clientes.
        </p>
    </div>
</section>
@endsection
