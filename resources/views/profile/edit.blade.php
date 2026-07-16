@extends('layouts.app')

@section('title', 'Meu perfil — Agenda Iwori')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-2xl font-semibold text-mvindigo">Meu perfil</h1>

    @if (session('status'))
        <div class="bg-mvteal-light border border-mvteal/30 text-mvteal-dark rounded-lg px-4 py-3">
            {{ session('status') }}
        </div>
    @endif

    {{-- Foto de perfil --}}
    <div class="bg-white rounded-2xl shadow p-8">
        <h2 class="text-lg font-semibold text-mvindigo mb-4">Foto de perfil</h2>

        <div class="flex items-center gap-6">
            @if ($user->photoUrl())
                <img src="{{ $user->photoUrl() }}" alt="Foto de perfil"
                     class="w-20 h-20 rounded-full object-cover border border-gray-200">
            @else
                <div class="w-20 h-20 rounded-full bg-mvrose text-white flex items-center justify-center text-2xl font-semibold">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </div>
            @endif

            <div class="space-y-3">
                <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data" class="flex items-center gap-3">
                    @csrf
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required
                           class="text-sm text-gray-600 file:mr-3 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-mvindigo file:text-white file:text-sm file:font-medium hover:file:bg-mvindigo/90 file:cursor-pointer">
                    <button type="submit" class="bg-mvteal hover:bg-mvteal-dark text-white text-sm font-medium px-4 py-2 rounded-lg shrink-0">
                        Enviar
                    </button>
                </form>

                @if ($user->photo_path)
                    <form method="POST" action="{{ route('profile.photo.destroy') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:underline">Remover foto</button>
                    </form>
                @endif

                @error('photo', 'photo')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="text-xs text-gray-500">JPG, PNG ou WebP, até 2 MB.</p>
            </div>
        </div>
    </div>

    {{-- Dados básicos --}}
    <div class="bg-white rounded-2xl shadow p-8">
        <h2 class="text-lg font-semibold text-mvindigo mb-4">Dados básicos</h2>

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label for="name" class="block text-sm font-medium mb-1">Nome *</label>
                <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required
                       class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                @error('name')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium mb-1">E-mail *</label>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required
                       class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                @error('email')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-2">
                <button type="submit" class="bg-mvteal hover:bg-mvteal-dark text-white font-medium px-5 py-2.5 rounded-lg">
                    Salvar alterações
                </button>
            </div>
        </form>
    </div>

    {{-- Alterar senha --}}
    <div class="bg-white rounded-2xl shadow p-8">
        <h2 class="text-lg font-semibold text-mvindigo mb-4">Alterar senha</h2>

        <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="block text-sm font-medium mb-1">Senha atual *</label>
                <input id="current_password" type="password" name="current_password" required autocomplete="current-password"
                       class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                @error('current_password', 'password')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-medium mb-1">Nova senha *</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                           class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                    @error('password', 'password')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium mb-1">Confirmar nova senha *</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                           class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="bg-mvindigo hover:bg-mvindigo/90 text-white font-medium px-5 py-2.5 rounded-lg">
                    Alterar senha
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
