{{-- Espera: $type (EmailTemplateType) e opcionalmente $template --}}
@csrf

<div>
    <label for="name" class="block text-sm font-medium mb-1">Nome do template *</label>
    <input id="name" type="text" name="name" value="{{ old('name', $template->name ?? '') }}" required maxlength="100"
           class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
    @error('name')
        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="subject" class="block text-sm font-medium mb-1">Assunto *</label>
    <input id="subject" type="text" name="subject" value="{{ old('subject', $template->subject ?? $type->defaultSubject()) }}" required maxlength="150"
           class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">
    @error('subject')
        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="body" class="block text-sm font-medium mb-1">Corpo do e-mail *</label>
    <textarea id="body" name="body" rows="8" required maxlength="5000" x-ref="body"
              class="w-full rounded-lg border-gray-300 border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mvteal">{{ old('body', $template->body ?? $type->defaultBody()) }}</textarea>
    @error('body')
        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
    @enderror
    <p class="text-xs text-gray-500 mt-1">
        O corpo aparece na abertura do e-mail; os detalhes (tabela de sessões, totais ou dados
        da sessão) são incluídos automaticamente na sequência.
    </p>
</div>

<div class="bg-mvsand rounded-xl p-4">
    <p class="text-sm font-semibold text-mvindigo mb-2">Variáveis disponíveis</p>
    <ul class="space-y-1">
        @foreach ($type->variables() as $variable => $description)
            <li class="flex items-center gap-2 text-sm">
                <button type="button"
                        @click="insertVariable('{{ $variable }}')"
                        class="font-mono text-xs bg-white border border-gray-200 rounded px-2 py-0.5 text-mvteal-dark hover:border-mvteal"
                        title="Inserir no corpo">{{ '{{'.$variable.'}}' }}</button>
                <span class="text-gray-600">{{ $description }}</span>
            </li>
        @endforeach
    </ul>
</div>
