{{--
    Campo de senha com botão para mostrar ou ocultar o que foi digitado.

    Uso: <x-password-input id="password" name="password" autocomplete="new-password" required />

    Os demais atributos (required, autofocus, autocomplete, x-bind:required...) vão
    direto para o <input>. Bindings do Alpine precisam da forma longa (x-bind:),
    porque ":atributo" em componente Blade é interpretado como PHP.
    Sem JavaScript o campo continua um password comum; o botão só funciona com Alpine.
--}}
@props(['id', 'name'])

<div class="relative" x-data="{ visivel: false }">
    <input id="{{ $id }}" name="{{ $name }}" type="password" x-bind:type="visivel ? 'text' : 'password'"
           {{ $attributes->merge(['class' => 'w-full rounded-xl border border-line-strong bg-white pl-3.5 pr-12 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25']) }}>
    <button type="button"
            x-on:click="visivel = ! visivel"
            aria-controls="{{ $id }}"
            aria-pressed="false" x-bind:aria-pressed="visivel.toString()"
            aria-label="Mostrar senha" x-bind:aria-label="visivel ? 'Ocultar senha' : 'Mostrar senha'"
            title="Mostrar senha" x-bind:title="visivel ? 'Ocultar senha' : 'Mostrar senha'"
            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-muted hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40">
        {{-- Olho aberto: senha escondida, clique para mostrar. --}}
        <svg x-show="! visivel" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
        </svg>
        {{-- Olho riscado: senha visível, clique para ocultar. --}}
        <svg x-cloak x-show="visivel" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
        </svg>
    </button>
</div>
