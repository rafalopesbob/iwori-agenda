<?php

namespace App\Http\Controllers;

use App\Enums\EmailTemplateType;
use App\Enums\MessageChannel;
use App\Http\Requests\StoreEmailTemplateRequest;
use App\Http\Requests\UpdateEmailTemplateRequest;
use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    /**
     * Tela "Mensagens": os textos de e-mail e de WhatsApp do profissional
     * e os tipos ainda no padrão do sistema.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', EmailTemplate::class);

        $templates = $request->user()
            ->emailTemplates()
            ->orderBy('type')
            ->get()
            ->keyBy(fn (EmailTemplate $template) => $template->channel->value.'.'.$template->type->value);

        return view('email-templates.index', [
            'templates' => $templates,
            'types' => EmailTemplateType::cases(),
            'channels' => MessageChannel::cases(),
        ]);
    }

    /**
     * Exibe o formulário de criação, pré-preenchido com o padrão do sistema.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', EmailTemplate::class);

        $type = EmailTemplateType::tryFrom((string) $request->query('type'))
            ?? EmailTemplateType::Charge;
        $channel = MessageChannel::tryFrom((string) $request->query('channel'))
            ?? MessageChannel::Email;

        return view('email-templates.create', compact('type', 'channel'));
    }

    /**
     * Salva o texto personalizado do profissional.
     */
    public function store(StoreEmailTemplateRequest $request): RedirectResponse
    {
        $request->user()->emailTemplates()->create($request->validated());

        return redirect()->route('email-templates.index')
            ->with('status', 'Texto personalizado salvo.');
    }

    /**
     * Exibe o formulário de edição.
     */
    public function edit(EmailTemplate $emailTemplate): View
    {
        $this->authorize('update', $emailTemplate);

        return view('email-templates.edit', ['template' => $emailTemplate]);
    }

    /**
     * Atualiza o template.
     */
    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $emailTemplate->update($request->validated());

        return redirect()->route('email-templates.index')
            ->with('status', 'Texto atualizado.');
    }

    /**
     * Remove o template — o tipo volta ao padrão do sistema.
     */
    public function destroy(EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->authorize('delete', $emailTemplate);

        $emailTemplate->delete();

        return redirect()->route('email-templates.index')
            ->with('status', 'Texto removido. O padrão do sistema volta a ser usado.');
    }

    /**
     * Pré-visualiza o texto com dados de exemplo, sem salvar.
     */
    public function preview(Request $request, EmailTemplateService $service): View
    {
        $this->authorize('viewAny', EmailTemplate::class);

        $channel = MessageChannel::tryFrom((string) $request->input('channel')) ?? MessageChannel::Email;

        $validated = $request->validate([
            'type' => ['required', Rule::enum(EmailTemplateType::class)],
            'subject' => $channel === MessageChannel::Email ? ['required', 'string', 'max:150'] : ['nullable'],
            'body' => ['required', 'string', 'max:'.$channel->maxBodyLength()],
        ]);

        $type = EmailTemplateType::from($validated['type']);

        return view('email-templates.preview', [
            'type' => $type,
            'channel' => $channel,
            'subject' => $channel === MessageChannel::Email
                ? $service->render($validated['subject'], $type->sampleData())
                : null,
            'body' => $service->render($validated['body'], $type->sampleData()),
        ]);
    }
}
