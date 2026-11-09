<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Agenda Iwori')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas font-sans text-ink antialiased">
@auth
    @php
        $navItems = [
            ['route' => 'dashboard', 'active' => 'dashboard', 'label' => 'Painel', 'mobile' => true, 'icon' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z'],
            ['route' => 'sessions.index', 'active' => 'sessions.*', 'label' => 'Agenda', 'mobile' => true, 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
            ['route' => 'clients.index', 'active' => 'clients.*', 'label' => 'Clientes', 'mobile' => true, 'icon' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'],
            ['route' => 'billing.index', 'active' => 'billing.*', 'label' => 'Faturamento', 'mobile' => false, 'icon' => 'M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            ['route' => 'charges.index', 'active' => 'charges.*', 'label' => 'Cobranças', 'mobile' => true, 'icon' => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z'],
            ['route' => 'email-templates.index', 'active' => 'email-templates.*', 'label' => 'E-mails', 'mobile' => false, 'icon' => 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75'],
        ];
        $user = auth()->user();
        $initial = mb_strtoupper(mb_substr($user->name, 0, 1));
        // "Mais" fica ativo no celular quando a página atual só existe nesse menu.
        $moreActive = request()->routeIs('billing.*', 'email-templates.*', 'profile.*');
    @endphp

    {{-- Barra superior (celular) --}}
    <header class="lg:hidden sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-line">
        <div class="flex items-center justify-between gap-3 px-4 h-16">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 text-[17px] font-semibold tracking-tight text-ink">
                <x-brand-mark class="size-6" />
                <span>Ìwòrì <span class="font-medium text-muted">Agenda</span></span>
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('sessions.create') }}" aria-label="Nova sessão"
                   class="flex items-center justify-center size-11 rounded-xl bg-brand text-white hover:bg-brand-dark transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-5" aria-hidden="true">
                        <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                    </svg>
                </a>
                <a href="{{ route('profile.edit') }}" title="Meu perfil" aria-label="Meu perfil"
                   class="flex items-center justify-center size-11 rounded-full">
                    @if ($user->photoUrl())
                        <img src="{{ $user->photoUrl() }}" alt="" class="size-9 rounded-full object-cover">
                    @else
                        <span class="flex items-center justify-center size-9 rounded-full bg-brand-tint text-brand font-semibold">{{ $initial }}</span>
                    @endif
                </a>
            </div>
        </div>
    </header>

    {{-- Menu lateral (desktop) --}}
    <aside class="hidden lg:flex flex-col fixed inset-y-0 left-0 w-64 bg-white border-r border-line px-4 py-6 gap-6 overflow-y-auto">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-2 text-lg font-semibold tracking-tight text-ink">
            <x-brand-mark class="size-7" />
            <span>Ìwòrì <span class="font-medium text-muted">Agenda</span></span>
        </a>

        <nav aria-label="Menu principal" class="flex flex-col gap-0.5">
            @foreach ($navItems as $item)
                @php $active = request()->routeIs($item['active']); @endphp
                <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                   class="flex items-center gap-3 min-h-11 px-3 rounded-xl text-[15px] font-medium transition-colors
                          {{ $active ? 'bg-brand-tint text-brand' : 'text-muted hover:bg-canvas hover:text-ink' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-5 shrink-0" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <a href="{{ route('sessions.create') }}"
           class="flex items-center justify-center gap-2 min-h-11 rounded-xl bg-brand hover:bg-brand-dark text-white text-[15px] font-semibold transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-[18px]" aria-hidden="true">
                <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
            </svg>
            Nova sessão
        </a>

        <div class="mt-auto flex flex-col gap-3">
            @if (config('services.google.client_id'))
                <div class="rounded-2xl border border-line p-3.5 flex gap-2.5 items-start">
                    <span class="mt-1.5 size-2 rounded-full shrink-0 {{ $user->hasGoogleCalendar() ? 'bg-accent' : 'bg-line-strong' }}"></span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">Google Agenda</p>
                        @if ($user->hasGoogleCalendar())
                            <p class="text-[13px] leading-snug text-muted mt-0.5">Conectado. Suas sessões aparecem no Google Agenda.</p>
                            <div class="mt-1 flex flex-wrap gap-x-3">
                                <form method="POST" action="{{ route('google.disconnect') }}">
                                    @csrf
                                    <button type="submit" class="text-[13px] font-medium text-danger-ink hover:underline">Desconectar</button>
                                </form>
                                <a href="{{ route('help.google-calendar') }}" class="text-[13px] font-medium text-muted hover:text-ink hover:underline">Como funciona</a>
                            </div>
                        @else
                            <p class="text-[13px] leading-snug text-muted mt-0.5">Veja suas sessões no Google Agenda, com lembrete no celular.</p>
                            <div class="mt-1 flex flex-wrap gap-x-3">
                                <a href="{{ route('google.connect') }}" class="text-[13px] font-semibold text-brand hover:underline">Conectar</a>
                                <a href="{{ route('help.google-calendar') }}" class="text-[13px] font-medium text-muted hover:text-ink hover:underline">Como funciona</a>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="flex items-center gap-2">
                <a href="{{ route('profile.edit') }}" title="Meu perfil"
                   class="flex flex-1 items-center gap-3 min-w-0 rounded-xl p-2 hover:bg-canvas transition-colors">
                    @if ($user->photoUrl())
                        <img src="{{ $user->photoUrl() }}" alt="" class="size-9 rounded-full object-cover shrink-0">
                    @else
                        <span class="flex items-center justify-center size-9 rounded-full bg-brand-tint text-brand font-semibold shrink-0">{{ $initial }}</span>
                    @endif
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold truncate">{{ $user->name }}</span>
                        <span class="block text-[13px] text-muted truncate">{{ $user->email }}</span>
                    </span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Sair" aria-label="Sair"
                            class="flex items-center justify-center size-11 rounded-xl text-muted hover:bg-canvas hover:text-danger-ink transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="lg:pl-64">
        @include('layouts.partials.demo-banner')

        @if (! auth()->user()->hasVerifiedEmail() && ! request()->routeIs('verification.notice'))
            <div role="status" class="border-b border-warn/30 bg-warn-tint text-warn-ink">
                <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                    <span>Confirme seu e-mail para liberar o envio de confirmações e cobranças aos seus clientes.</span>
                    <a href="{{ route('verification.notice') }}" class="inline-flex items-center min-h-10 font-semibold underline">Confirmar e-mail</a>
                </div>
            </div>
        @endif

        @hasSection('bleed')
            <main class="pb-24 lg:pb-0">@yield('content')</main>
        @else
            <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 lg:pt-8 pb-28 lg:pb-12">
                @yield('content')
            </main>
        @endif
    </div>

    {{-- Barra de abas (celular) --}}
    <nav aria-label="Menu principal" x-data="{ more: false }" @keydown.escape.window="more = false"
         class="lg:hidden fixed inset-x-0 bottom-0 z-30 bg-white border-t border-line">
        <div x-cloak x-show="more" x-transition.opacity class="fixed inset-0 bg-ink/30" @click="more = false"></div>
        <div x-cloak x-show="more" x-transition
             class="absolute bottom-full right-3 mb-2 w-60 rounded-2xl border border-line bg-white p-2 shadow-xl">
            @foreach ($navItems as $item)
                @continue($item['mobile'])
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 min-h-11 px-3 rounded-xl text-[15px] font-medium {{ request()->routeIs($item['active']) ? 'bg-brand-tint text-brand' : 'text-ink hover:bg-canvas' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-5 shrink-0" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
            <a href="{{ route('profile.edit') }}"
               class="flex items-center gap-3 min-h-11 px-3 rounded-xl text-[15px] font-medium {{ request()->routeIs('profile.*') ? 'bg-brand-tint text-brand' : 'text-ink hover:bg-canvas' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-5 shrink-0" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
                Meu perfil
            </a>
            <form method="POST" action="{{ route('logout') }}" class="border-t border-line mt-1 pt-1">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 min-h-11 px-3 rounded-xl text-[15px] font-medium text-danger-ink hover:bg-danger-tint">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-5 shrink-0" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                    </svg>
                    Sair
                </button>
            </form>
        </div>

        <div class="relative flex px-1 pb-[env(safe-area-inset-bottom)]">
            @foreach ($navItems as $item)
                @continue(! $item['mobile'])
                @php $active = request()->routeIs($item['active']); @endphp
                <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                   class="flex flex-1 flex-col items-center justify-center gap-1 h-16 text-xs {{ $active ? 'font-semibold text-brand' : 'font-medium text-muted' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-[22px]" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
            <button type="button" @click="more = ! more" :aria-expanded="more.toString()"
                    class="flex flex-1 flex-col items-center justify-center gap-1 h-16 text-xs {{ $moreActive ? 'font-semibold text-brand' : 'font-medium text-muted' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-[22px]" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                </svg>
                Mais
            </button>
        </div>
    </nav>
@else
    @include('layouts.partials.demo-banner')

    <header class="max-w-6xl mx-auto px-4 sm:px-6 py-5 flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 text-[19px] font-semibold tracking-tight text-ink">
            <x-brand-mark class="size-7" />
            <span>Ìwòrì <span class="font-medium text-muted">Agenda</span></span>
        </a>
        <nav aria-label="Principal" class="flex flex-wrap items-center gap-1">
            <a href="{{ route('home') }}#recursos" class="hidden md:inline-flex items-center min-h-11 px-3 text-[15px] font-medium text-muted hover:text-ink">Recursos</a>
            <a href="{{ route('home') }}#como-funciona" class="hidden md:inline-flex items-center min-h-11 px-3 text-[15px] font-medium text-muted hover:text-ink">Como funciona</a>
            <a href="{{ route('home') }}#para-quem" class="hidden md:inline-flex items-center min-h-11 px-3 text-[15px] font-medium text-muted hover:text-ink">Para quem</a>
            <a href="{{ route('login') }}" class="inline-flex items-center min-h-11 px-[18px] md:ml-2 rounded-full border border-line-strong bg-white text-[15px] font-semibold text-ink hover:bg-canvas">Entrar</a>
            @unless (config('demo.enabled'))
                <a href="{{ route('register') }}" class="inline-flex items-center min-h-11 px-[18px] rounded-full bg-brand hover:bg-brand-dark text-[15px] font-semibold text-white">Criar conta</a>
            @endunless
        </nav>
    </header>

    @hasSection('bleed')
        <main>@yield('content')</main>
    @else
        <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
            @yield('content')
        </main>
    @endif

    <footer class="border-t border-line">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 text-sm text-muted">
            © {{ date('Y') }} Ìwòrì Agenda
        </div>
    </footer>
@endauth
</body>
</html>
