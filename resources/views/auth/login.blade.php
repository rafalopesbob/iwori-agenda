@extends('layouts.app')

@section('title', 'Entrar — Iwori Agenda')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-2xl shadow p-8">
    <h1 class="text-2xl font-semibold text-gray-900 mb-6">Entrar</h1>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium mb-1">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            @error('email')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium mb-1">Senha</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            @error('password')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" class="rounded border-gray-300 text-indigo-600">
            Manter conectado
        </label>

        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg">
            Entrar
        </button>
    </form>

    <p class="text-sm text-gray-600 mt-6 text-center">
        Ainda não tem conta?
        <a href="{{ route('register') }}" class="text-indigo-600 font-medium hover:underline">Criar conta</a>
    </p>
</div>
@endsection
