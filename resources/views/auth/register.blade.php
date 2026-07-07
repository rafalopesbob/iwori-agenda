@extends('layouts.app')

@section('title', 'Criar conta — Agenda Iwori')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-2xl shadow p-8">
    <h1 class="text-2xl font-semibold text-mvindigo mb-6">Criar conta</h1>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium mb-1">Nome</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                   class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
            @error('name')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium mb-1">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                   class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
            @error('email')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium mb-1">Senha</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                   class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
            <p class="text-xs text-gray-500 mt-1">Mínimo de 12 caracteres, com maiúsculas, minúsculas, números e símbolos.</p>
            @error('password')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium mb-1">Confirmar senha</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                   class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
        </div>

        <button type="submit" class="w-full bg-mvteal hover:bg-mvteal-dark text-white font-medium py-2.5 rounded-lg">
            Criar conta
        </button>
    </form>

    <p class="text-sm text-gray-600 mt-6 text-center">
        Já tem conta?
        <a href="{{ route('login') }}" class="text-mvteal-dark font-medium hover:underline">Entrar</a>
    </p>
</div>
@endsection
