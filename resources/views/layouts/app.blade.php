<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Iwori Agenda')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-emerald-50 text-gray-800 antialiased">
    <nav class="bg-white border-b border-emerald-100 shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ route('home') }}" class="text-lg font-semibold text-emerald-700">🌿 Iwori Agenda</a>
            <div class="flex items-center gap-4 text-sm">
                @auth
                    <a href="{{ route('sessions.index') }}" class="text-emerald-700 hover:text-emerald-900 font-medium">Agenda</a>
                    <a href="{{ route('clients.index') }}" class="text-emerald-700 hover:text-emerald-900 font-medium">Clientes</a>
                    <a href="{{ route('billing.index') }}" class="text-emerald-700 hover:text-emerald-900 font-medium">Faturamento</a>
                    <span class="text-gray-600">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-emerald-700 hover:text-emerald-900 font-medium">Sair</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-emerald-700 hover:text-emerald-900 font-medium">Entrar</a>
                    <a href="{{ route('register') }}" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg font-medium">Criar conta</a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-8">
        @yield('content')
    </main>
</body>
</html>
