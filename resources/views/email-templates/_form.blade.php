{{-- Espera: $type (EmailTemplateType) e opcionalmente $template --}}
@csrf

<div>
    <label for="name" class="block text-sm font-medium text-ink mb-1.5">Nome do template *</label>
    <input id="name" type="text" name="name" value="{{ old('name', $template->name ?? '') }}" required maxlength="100"
           class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
    @error('name')
        <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="subject" class="block text-sm font-medium text-ink mb-1.5">Assunto *</label>
    <input id="subject" type="text" name="subject" value="{{ old('subject', $template->subject ?? $type->defaultSubject()) }}" required maxlength="150"
           class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">
    @error('subject')
        <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="body" class="block text-sm font-medium text-ink mb-1.5">Corpo do e-mail *</label>
    <textarea id="body" name="body" rows="8" required maxlength="5000" x-ref="body"
              class="w-full rounded-xl border border-line-strong bg-white px-3.5 py-2.5 text-[15px] text-ink leading-relaxed placeholder:text-subtle focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/25">{{ old('body', $template->body ?? $type->defaultBody()) }}</textarea>
    @error('body')
        <p class="text-sm text-danger-ink mt-1.5">{{ $message }}</p>
    @enderror
    <p class="text-[13px] text-muted mt-1.5">
        O corpo aparece na abertura do e-mail; os detalhes (tabela de sessões, totais ou dados
        da sessão) são incluídos automaticamente na sequência.
    </p>
</div>

<div class="rounded-2xl border border-line bg-canvas p-5">
    <p class="text-sm font-semibold text-ink mb-3">Variáveis disponíveis</p>
    <ul class="space-y-2">
        @foreach ($type->variables() as $variable => $description)
            <li class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <button type="button"
                        @click="insertVariable('{{ $variable }}')"
                        class="inline-flex items-center min-h-11 px-3 rounded-lg border border-line-strong bg-white font-mono text-[13px] text-brand hover:border-brand hover:bg-brand-tint transition-colors"
                        title="Inserir no corpo">{{ '{{'.$variable.'}}' }}</button>
                <span class="text-muted">{{ $description }}</span>
            </li>
        @endforeach
    </ul>
</div>
