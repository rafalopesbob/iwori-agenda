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

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-5"
              x-data="{ email: @js(old('email', $user->email)), original: @js($user->email) }">
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
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required x-model="email"
                       class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                @error('email')
                    <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
                @enderror
            </div>

            {{-- Só aparece ao trocar o e-mail: a troca exige confirmar a senha atual. --}}
            @if ($user->hasPassword())
                <div x-cloak x-show="email !== original">
                    <label for="email_current_password" class="block text-sm font-medium text-ink mb-1.5">Senha atual *</label>
                    <x-password-input id="email_current_password" name="email_current_password" autocomplete="current-password"
                                      x-bind:required="email !== original" />
                    <p class="text-sm text-muted mt-1.5">Para trocar o e-mail da conta, confirme sua senha.</p>
                    @error('email_current_password')
                        <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
                    @enderror
                </div>
            @else
                {{-- Conta criada pelo Google ainda sem senha: não há o que confirmar.
                     Com o erro de validação já na tela, a dica repetiria a mensagem. --}}
                @unless ($errors->has('email'))
                    <p x-cloak x-show="email !== original" class="text-sm text-muted">
                        Para trocar o e-mail da conta, defina uma senha antes, em "Definir senha", logo abaixo.
                    </p>
                @endunless
            @endif

            <div class="pt-2">
                <button type="submit" class="inline-flex items-center justify-center gap-2 min-h-11 px-5 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors">
                    Salvar alterações
                </button>
            </div>
        </form>
    </div>

    {{-- Conexões: por onde as mensagens chegam aos clientes e a agenda do Google --}}
    <div id="conexoes" class="bg-white rounded-2xl border border-line p-6 sm:p-8 scroll-mt-6">
        <h2 class="text-lg font-semibold tracking-tight text-ink">Conexões</h2>
        <p class="text-sm text-muted mt-1 mb-6">Como suas mensagens chegam aos clientes e o que fica ligado à sua conta.</p>

        <div class="divide-y divide-line">
            {{-- E-mail --}}
            <section class="pb-6">
                <h3 class="font-semibold text-ink">E-mail</h3>
                <p class="text-sm text-muted mt-1">
                    Os e-mails aos clientes saem do endereço do Agenda Iwori, com o seu nome como remetente.
                    Quando o cliente responde, a resposta vai para o e-mail abaixo.
                </p>

                <form method="POST" action="{{ route('profile.reply-to') }}" class="mt-4 flex flex-col sm:flex-row gap-3 sm:items-start">
                    @csrf
                    @method('PATCH')
                    <div class="flex-1 min-w-0">
                        <label for="reply_to_email" class="block text-sm font-medium text-ink mb-1.5">E-mail para respostas</label>
                        <input id="reply_to_email" type="email" name="reply_to_email" maxlength="255"
                               value="{{ old('reply_to_email', $user->reply_to_email) }}" placeholder="{{ $user->email }}"
                               class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
                        @error('reply_to_email')
                            <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
                        @enderror
                        <p class="text-[13px] text-muted mt-1.5">Em branco, as respostas vão para o e-mail da sua conta.</p>
                    </div>
                    <button type="submit" class="sm:mt-7 inline-flex items-center justify-center gap-2 min-h-11 px-4 rounded-xl border border-line-strong bg-white text-ink hover:bg-canvas text-sm font-semibold transition-colors shrink-0">
                        Salvar
                    </button>
                </form>

                <a href="{{ route('email-templates.index') }}#email" class="inline-flex items-center min-h-11 mt-1 text-sm font-medium text-brand hover:underline">Editar os textos dos e-mails</a>
            </section>

            {{-- WhatsApp --}}
            <section class="py-6">
                <h3 class="font-semibold text-ink">WhatsApp</h3>
                <p class="text-sm text-muted mt-1">
                    As mensagens saem do <strong class="font-semibold text-ink">seu</strong> WhatsApp: nas cobranças e nas sessões,
                    o botão “WhatsApp” abre a conversa com o cliente e o texto pronto. Você confere e toca em enviar.
                    Não há nada para configurar; basta o celular do cliente estar no cadastro.
                </p>
                <p class="text-[13px] text-muted mt-2">
                    @if ($whatsappAutomatic)
                        Envio automático: ativo para os clientes que autorizaram.
                    @else
                        Envio automático, sem precisar tocar em enviar: ainda não disponível.
                    @endif
                </p>
                <a href="{{ route('email-templates.index') }}#whatsapp" class="inline-flex items-center min-h-11 mt-1 text-sm font-medium text-brand hover:underline">Editar os textos do WhatsApp</a>
            </section>

            {{-- Google Agenda --}}
            <section class="pt-6">
                <h3 class="font-semibold text-ink">Google Agenda</h3>
                @if (! $googleConfigured)
                    <p class="text-sm text-muted mt-1">A integração com o Google Agenda ainda não está disponível.</p>
                @elseif ($user->hasGoogleCalendar())
                    <p class="text-sm text-muted mt-1">Conectado. Suas sessões aparecem no seu Google Agenda.</p>
                    <div class="mt-2 flex flex-wrap items-center gap-x-4">
                        <form method="POST" action="{{ route('google.disconnect') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center min-h-11 text-sm font-medium text-danger-ink hover:underline">Desconectar</button>
                        </form>
                        <a href="{{ route('help.google-calendar') }}" class="inline-flex items-center min-h-11 text-sm font-medium text-muted hover:text-ink hover:underline">Como funciona</a>
                    </div>
                @else
                    <p class="text-sm text-muted mt-1">Veja suas sessões no Google Agenda, com lembrete no celular.</p>
                    <div class="mt-2 flex flex-wrap items-center gap-x-4">
                        <a href="{{ route('google.connect') }}" class="inline-flex items-center min-h-11 text-sm font-semibold text-brand hover:underline">Conectar Google Agenda</a>
                        <a href="{{ route('help.google-calendar') }}" class="inline-flex items-center min-h-11 text-sm font-medium text-muted hover:text-ink hover:underline">Como funciona</a>
                    </div>
                @endif
            </section>
        </div>
    </div>

    {{-- Alterar senha (ou definir a primeira, para quem se cadastrou pelo Google) --}}
    <div class="bg-white rounded-2xl border border-line p-6 sm:p-8">
        @if ($user->hasPassword())
            <h2 class="text-lg font-semibold tracking-tight text-ink mb-5">Alterar senha</h2>
        @else
            <h2 class="text-lg font-semibold tracking-tight text-ink">Definir senha</h2>
            <p class="text-sm text-muted mt-1 mb-5">Você entra pelo Google. Se quiser, defina uma senha para entrar também com e-mail e senha.</p>
        @endif

        <form method="POST" action="{{ route('profile.password') }}" class="space-y-5">
            @csrf
            @method('PUT')

            @if ($user->hasPassword())
                <div>
                    <label for="current_password" class="block text-sm font-medium text-ink mb-1.5">Senha atual *</label>
                    <x-password-input id="current_password" name="current_password" required autocomplete="current-password" />
                    @error('current_password', 'password')
                        <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-ink mb-1.5">Nova senha *</label>
                    <x-password-input id="password" name="password" required autocomplete="new-password" />
                    @error('password', 'password')
                        <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-ink mb-1.5">Confirmar nova senha *</label>
                    <x-password-input id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="inline-flex items-center justify-center gap-2 min-h-11 px-5 rounded-xl bg-brand hover:bg-brand-dark text-white text-sm font-semibold transition-colors">
                    {{ $user->hasPassword() ? 'Alterar senha' : 'Definir senha' }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
