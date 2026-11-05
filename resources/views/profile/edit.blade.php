@extends('layouts.app')

@section('title', 'Meu perfil — Agenda Iwori')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-3xl font-semibold tracking-tight text-ink">Meu perfil</h1>

    @if (session('status'))
        <div class="rounded-xl border border-accent/20 bg-accent-tint px-4 py-3 text-sm font-medium text-accent-ink">
            {{ session('status') }}
        </div>
    @endif

    {{-- Foto de perfil --}}
    <div class="bg-white rounded-2xl border border-line p-6 sm:p-8">
        <h2 class="text-lg font-semibold tracking-tight text-ink mb-5">Foto de perfil</h2>

        <div class="flex flex-col sm:flex-row sm:items-center gap-5 sm:gap-6">
            @if ($user->photoUrl())
                <img src="{{ $user->photoUrl() }}" alt="Foto de perfil"
                     class="size-20 shrink-0 rounded-full object-cover border border-line">
            @else
                <div class="size-20 shrink-0 rounded-full bg-brand-tint text-brand flex items-center justify-center text-2xl font-semibold">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </div>
            @endif

            <div class="min-w-0 space-y-3">
                <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                    @csrf
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required
                           class="min-w-0 max-w-full text-sm text-muted file:min-h-11 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-brand file:text-white file:text-sm file:font-semibold hover:file:bg-brand-dark file:cursor-pointer">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 min-h-11 px-4 rounded-xl border border-line-strong bg-white text-ink hover:bg-canvas text-sm font-semibold transition-colors shrink-0">
                        Enviar
                    </button>
                </form>

                @if ($user->photo_path)
                    <form method="POST" action="{{ route('profile.photo.destroy') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center min-h-11 text-sm font-medium text-danger-ink hover:underline">Remover foto</button>
                    </form>
                @endif

                @error('photo', 'photo')
                    <p class="text-sm text-danger-ink">{{ $message }}</p>
                @enderror
                <p class="text-[13px] text-muted">JPG, PNG ou WebP, até 2 MB.</p>
            </div>
        </div>
    </div>

    {{-- Dados básicos --}}
    <div class="bg-white rounded-2xl border border-line p-6 sm:p-8">
        <h2 class="text-lg font-semibold tracking-tight text-ink mb-5">Dados básicos</h2>

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <div>
                <label for="name" class="block text-sm font-medium text-ink mb-1.5">Nome *</label>
                <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required
                       class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                @error('name')
                    <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-ink mb-1.5">E-mail *</label>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required
                       class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                @error('email')
                    <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
                @enderror
            </div>

            <div class="pt-2">
                <button type="submit" class="inline-flex items-center justify-center gap-2 min-h-11 px-5 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors">
                    Salvar alterações
                </button>
            </div>
        </form>
    </div>

    {{-- Alterar senha --}}
    <div class="bg-white rounded-2xl border border-line p-6 sm:p-8">
        <h2 class="text-lg font-semibold tracking-tight text-ink mb-5">Alterar senha</h2>

        <form method="POST" action="{{ route('profile.password') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="block text-sm font-medium text-ink mb-1.5">Senha atual *</label>
                <input id="current_password" type="password" name="current_password" required autocomplete="current-password"
                       class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                @error('current_password', 'password')
                    <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-ink mb-1.5">Nova senha *</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                           class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                    @error('password', 'password')
                        <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-ink mb-1.5">Confirmar nova senha *</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                           class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="inline-flex items-center justify-center gap-2 min-h-11 px-5 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors">
                    Alterar senha
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
