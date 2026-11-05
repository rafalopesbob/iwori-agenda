@csrf

@php
    $field = 'w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25';
    $label = 'block text-sm font-medium text-ink mb-1.5';
    $error = 'text-sm text-danger-ink mt-1.5';
@endphp

<div>
    <label for="name" class="{{ $label }}">Nome *</label>
    <input id="name" type="text" name="name" value="{{ old('name', $client->name ?? '') }}" required class="{{ $field }}">
    @error('name')
        <p class="{{ $error }}">{{ $message }}</p>
    @enderror
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label for="email" class="{{ $label }}">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email', $client->email ?? '') }}" class="{{ $field }}">
        @error('email')
            <p class="{{ $error }}">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="phone" class="{{ $label }}">Telefone</label>
        <input id="phone" type="text" name="phone" value="{{ old('phone', $client->phone ?? '') }}" placeholder="(11) 99999-9999" class="{{ $field }}">
        @error('phone')
            <p class="{{ $error }}">{{ $message }}</p>
        @enderror
    </div>
</div>

<div>
    <label for="session_value" class="{{ $label }}">Valor da sessão (R$) *</label>
    <input id="session_value" type="number" name="session_value" step="0.01" min="0"
           value="{{ old('session_value', $client->session_value ?? '') }}" required class="{{ $field }}">
    @error('session_value')
        <p class="{{ $error }}">{{ $message }}</p>
    @enderror
</div>

<fieldset class="rounded-2xl border border-line p-5 space-y-4"
          x-data="{
              cycle: @js(old('payment_cycle', $client->payment_cycle->value ?? 'monthly')),
              channel: @js(old('billing_channel', $client->billing_channel->value ?? 'email')),
          }">
    <legend class="px-2 text-sm font-semibold text-ink">Cobrança</legend>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="payment_cycle" class="{{ $label }}">Ciclo de pagamento *</label>
            <select id="payment_cycle" name="payment_cycle" required x-model="cycle" class="{{ $field }}">
                @foreach (App\Enums\PaymentCycle::cases() as $cycle)
                    <option value="{{ $cycle->value }}"
                        @selected(old('payment_cycle', $client->payment_cycle->value ?? 'monthly') === $cycle->value)>
                        {{ $cycle->label() }}
                    </option>
                @endforeach
            </select>
            @error('payment_cycle')
                <p class="{{ $error }}">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="billing_channel" class="{{ $label }}">Canal de cobrança *</label>
            <select id="billing_channel" name="billing_channel" required x-model="channel" class="{{ $field }}">
                @foreach (App\Enums\BillingChannel::cases() as $channel)
                    <option value="{{ $channel->value }}"
                        @selected(old('billing_channel', $client->billing_channel->value ?? 'email') === $channel->value)>
                        {{ $channel->label() }}
                    </option>
                @endforeach
            </select>
            @error('billing_channel')
                <p class="{{ $error }}">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- Consentimento só faz sentido quando o canal inclui WhatsApp. --}}
    <div x-cloak x-show="channel !== 'email'" class="rounded-xl bg-canvas px-4 py-3">
        <label class="flex items-start gap-3 min-h-11 py-1 text-[15px]">
            <input type="hidden" name="whatsapp_opt_in" value="0">
            <input type="checkbox" name="whatsapp_opt_in" value="1"
                   @checked(old('whatsapp_opt_in', ($client->whatsapp_opt_in_at ?? null) !== null))
                   class="mt-0.5 size-4 rounded border-line-strong accent-brand">
            <span>
                O cliente autorizou receber cobranças por WhatsApp
                <span class="block text-[13px] text-muted mt-0.5">Sem essa autorização, a cobrança não é enviada pelo WhatsApp.</span>
            </span>
        </label>
        @error('whatsapp_opt_in')
            <p class="{{ $error }}">{{ $message }}</p>
        @enderror
    </div>

    {{-- As transições abrem/fecham como uma peça se encaixando: escala a
         partir do topo, sem movimento brusco. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div x-cloak x-show="cycle === 'monthly'"
             x-transition:enter="transition ease-out duration-200 origin-top"
             x-transition:enter-start="opacity-0 -translate-y-1 scale-y-90"
             x-transition:enter-end="opacity-100 translate-y-0 scale-y-100"
             x-transition:leave="transition ease-in duration-150 origin-top"
             x-transition:leave-start="opacity-100 scale-y-100"
             x-transition:leave-end="opacity-0 scale-y-90">
            <label for="payment_day" class="{{ $label }}">Dia de pagamento (1–31) *</label>
            <input id="payment_day" type="number" name="payment_day" min="1" max="31"
                   value="{{ old('payment_day', $client->payment_day ?? 5) }}" placeholder="Ex.: todo dia 5" class="{{ $field }}">
            @error('payment_day')
                <p class="{{ $error }}">{{ $message }}</p>
            @enderror
        </div>

        <div x-cloak x-show="cycle === 'interval'"
             x-transition:enter="transition ease-out duration-200 origin-top"
             x-transition:enter-start="opacity-0 -translate-y-1 scale-y-90"
             x-transition:enter-end="opacity-100 translate-y-0 scale-y-100"
             x-transition:leave="transition ease-in duration-150 origin-top"
             x-transition:leave-start="opacity-100 scale-y-100"
             x-transition:leave-end="opacity-0 scale-y-90">
            <label for="payment_interval_days" class="{{ $label }}">A cada quantos dias? *</label>
            <input id="payment_interval_days" type="number" name="payment_interval_days" min="1" max="365"
                   value="{{ old('payment_interval_days', $client->payment_interval_days ?? '') }}" placeholder="Ex.: 15" class="{{ $field }}">
            @error('payment_interval_days')
                <p class="{{ $error }}">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <p class="text-[13px] leading-relaxed text-muted">
        Ao fim de cada ciclo, a cobrança com o valor a pagar é enviada pelo canal escolhido.
        Faltas <strong class="font-semibold text-ink">não informadas</strong> são cobradas; faltas <strong class="font-semibold text-ink">informadas</strong> são abonadas.
    </p>
</fieldset>

<div>
    <label for="notes" class="{{ $label }}">Anotações</label>
    <textarea id="notes" name="notes" rows="4" class="{{ $field }}">{{ old('notes', $client->notes ?? '') }}</textarea>
    <p class="text-[13px] text-muted mt-1.5">Armazenadas criptografadas — visíveis apenas para você.</p>
    @error('notes')
        <p class="{{ $error }}">{{ $message }}</p>
    @enderror
</div>

<label class="flex items-center gap-3 min-h-11 text-[15px]">
    <input type="hidden" name="active" value="0">
    <input type="checkbox" name="active" value="1" @checked(old('active', $client->active ?? true))
           class="size-4 rounded border-line-strong accent-brand">
    Cliente ativo
</label>
