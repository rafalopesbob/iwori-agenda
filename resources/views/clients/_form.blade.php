@csrf

<div>
    <label for="name" class="block text-sm font-medium mb-1">Nome *</label>
    <input id="name" type="text" name="name" value="{{ old('name', $client->name ?? '') }}" required
           class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
    @error('name')
        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label for="email" class="block text-sm font-medium mb-1">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email', $client->email ?? '') }}"
               class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
        @error('email')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="phone" class="block text-sm font-medium mb-1">Telefone</label>
        <input id="phone" type="text" name="phone" value="{{ old('phone', $client->phone ?? '') }}" placeholder="(11) 99999-9999"
               class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
        @error('phone')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>

<div>
    <label for="session_value" class="block text-sm font-medium mb-1">Valor da sessão (R$) *</label>
    <input id="session_value" type="number" name="session_value" step="0.01" min="0"
           value="{{ old('session_value', $client->session_value ?? '') }}" required
           class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
    @error('session_value')
        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>

<fieldset class="border border-gray-200 rounded-xl p-4 space-y-4"
          x-data="{ cycle: '{{ old('payment_cycle', $client->payment_cycle->value ?? 'monthly') }}' }">
    <legend class="text-sm font-semibold text-mvindigo px-2">Cobrança</legend>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="payment_cycle" class="block text-sm font-medium mb-1">Ciclo de pagamento *</label>
            <select id="payment_cycle" name="payment_cycle" required x-model="cycle"
                    class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                @foreach (App\Enums\PaymentCycle::cases() as $cycle)
                    <option value="{{ $cycle->value }}"
                        @selected(old('payment_cycle', $client->payment_cycle->value ?? 'monthly') === $cycle->value)>
                        {{ $cycle->label() }}
                    </option>
                @endforeach
            </select>
            @error('payment_cycle')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="billing_channel" class="block text-sm font-medium mb-1">Canal de cobrança *</label>
            <select id="billing_channel" name="billing_channel" required
                    class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
                @foreach (App\Enums\BillingChannel::cases() as $channel)
                    <option value="{{ $channel->value }}"
                        @selected(old('billing_channel', $client->billing_channel->value ?? 'email') === $channel->value)>
                        {{ $channel->label() }}
                    </option>
                @endforeach
            </select>
            @error('billing_channel')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
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
            <label for="payment_day" class="block text-sm font-medium mb-1">Dia de pagamento (1–31) *</label>
            <input id="payment_day" type="number" name="payment_day" min="1" max="31"
                   value="{{ old('payment_day', $client->payment_day ?? 5) }}" placeholder="Ex.: todo dia 5"
                   class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
            @error('payment_day')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div x-cloak x-show="cycle === 'interval'"
             x-transition:enter="transition ease-out duration-200 origin-top"
             x-transition:enter-start="opacity-0 -translate-y-1 scale-y-90"
             x-transition:enter-end="opacity-100 translate-y-0 scale-y-100"
             x-transition:leave="transition ease-in duration-150 origin-top"
             x-transition:leave-start="opacity-100 scale-y-100"
             x-transition:leave-end="opacity-0 scale-y-90">
            <label for="payment_interval_days" class="block text-sm font-medium mb-1">A cada quantos dias? *</label>
            <input id="payment_interval_days" type="number" name="payment_interval_days" min="1" max="365"
                   value="{{ old('payment_interval_days', $client->payment_interval_days ?? '') }}" placeholder="Ex.: 15"
                   class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
            @error('payment_interval_days')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <p class="text-xs text-gray-500">
        Ao fim de cada ciclo, a cobrança com o valor a pagar é enviada pelo canal escolhido.
        Faltas <strong>não informadas</strong> são cobradas; faltas <strong>informadas</strong> são abonadas.
    </p>
</fieldset>

<div>
    <label for="notes" class="block text-sm font-medium mb-1">Anotações</label>
    <textarea id="notes" name="notes" rows="4"
              class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">{{ old('notes', $client->notes ?? '') }}</textarea>
    <p class="text-xs text-gray-500 mt-1">Armazenadas criptografadas — visíveis apenas para você.</p>
    @error('notes')
        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>

<label class="flex items-center gap-2 text-sm">
    <input type="hidden" name="active" value="0">
    <input type="checkbox" name="active" value="1" @checked(old('active', $client->active ?? true))
           class="rounded border-gray-300 text-mvteal">
    Cliente ativo
</label>
