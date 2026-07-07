@csrf

<div>
    <label for="name" class="block text-sm font-medium mb-1">Nome *</label>
    <input id="name" type="text" name="name" value="{{ old('name', $client->name ?? '') }}" required
           class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
    @error('name')
        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label for="email" class="block text-sm font-medium mb-1">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email', $client->email ?? '') }}"
               class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        @error('email')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="phone" class="block text-sm font-medium mb-1">Telefone</label>
        <input id="phone" type="text" name="phone" value="{{ old('phone', $client->phone ?? '') }}" placeholder="(11) 99999-9999"
               class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        @error('phone')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>

<div>
    <label for="session_value" class="block text-sm font-medium mb-1">Valor da sessão (R$) *</label>
    <input id="session_value" type="number" name="session_value" step="0.01" min="0"
           value="{{ old('session_value', $client->session_value ?? '') }}" required
           class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
    @error('session_value')
        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="notes" class="block text-sm font-medium mb-1">Anotações</label>
    <textarea id="notes" name="notes" rows="4"
              class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('notes', $client->notes ?? '') }}</textarea>
    <p class="text-xs text-gray-500 mt-1">Armazenadas criptografadas — visíveis apenas para você.</p>
    @error('notes')
        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>

<label class="flex items-center gap-2 text-sm">
    <input type="hidden" name="active" value="0">
    <input type="checkbox" name="active" value="1" @checked(old('active', $client->active ?? true))
           class="rounded border-gray-300 text-indigo-600">
    Cliente ativo
</label>
