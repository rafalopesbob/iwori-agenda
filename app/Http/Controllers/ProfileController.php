<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfilePhotoRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\GoogleCalendarService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    /**
     * Exibe a tela de edição do perfil do profissional autenticado.
     */
    public function edit(Request $request, GoogleCalendarService $calendar, WhatsAppService $whatsapp): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'googleConfigured' => $calendar->isConfigured(),
            'whatsappAutomatic' => $whatsapp->isConfigured(),
        ]);
    }

    /**
     * Define para onde vão as respostas dos clientes aos e-mails do sistema.
     * Vazio volta a usar o e-mail de acesso.
     */
    public function updateReplyTo(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            ['reply_to_email' => ['nullable', 'string', 'email', 'max:255']],
            [],
            ['reply_to_email' => 'e-mail para respostas'],
        );

        $request->user()->forceFill([
            'reply_to_email' => $validated['reply_to_email'] ?? null,
        ])->save();

        return redirect()->route('profile.edit')
            ->with('status', 'Pronto: as respostas dos seus clientes vão para '.$request->user()->replyToAddress().'.');
    }

    /**
     * Atualiza nome e e-mail do profissional.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->safe()->only(['name', 'email']);

        $emailChanged = $validated['email'] !== $user->email;

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->fill($validated)->save();

        // O novo endereço precisa ser confirmado antes de voltar a liberar os envios.
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('profile.edit')
                ->with('status', 'Dados atualizados. Enviamos um link de confirmação para '.$user->email.'.');
        }

        return redirect()->route('profile.edit')
            ->with('status', 'Dados atualizados com sucesso.');
    }

    /**
     * Altera a senha do profissional, ou define a primeira senha de quem
     * se cadastrou pelo Google.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $status = $user->hasPassword()
            ? 'Senha alterada com sucesso.'
            : 'Senha definida. Agora você também pode entrar com e-mail e senha.';

        $user->update([
            'password' => $request->validated('password'),
        ]);

        // Novo ID de sessão; as demais sessões da conta caem pelo middleware auth.session.
        $request->session()->regenerate();

        return redirect()->route('profile.edit')
            ->with('status', $status);
    }

    /**
     * Envia ou substitui a foto de perfil.
     */
    public function updatePhoto(UpdateProfilePhotoRequest $request): RedirectResponse
    {
        $user = $request->user();
        $disk = config('filesystems.uploads');

        if ($user->photo_path !== null) {
            Storage::disk($disk)->delete($user->photo_path);
        }

        $user->forceFill([
            'photo_path' => $request->file('photo')->store("avatars/{$user->id}", $disk),
        ])->save();

        return redirect()->route('profile.edit')
            ->with('status', 'Foto de perfil atualizada.');
    }

    /**
     * Remove a foto de perfil.
     */
    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->photo_path !== null) {
            Storage::disk(config('filesystems.uploads'))->delete($user->photo_path);
            $user->forceFill(['photo_path' => null])->save();
        }

        return redirect()->route('profile.edit')
            ->with('status', 'Foto de perfil removida.');
    }

    /**
     * Entrega a foto de perfil do próprio profissional.
     *
     * A foto fica no disco privado de uploads (local ou R2) e só é exibida para
     * o dono, então passa pelo app em vez de ter uma URL pública. O nome do
     * arquivo muda a cada envio, e o ?v= da URL troca junto: o navegador pode
     * guardar a imagem em cache sem nunca mostrar uma foto antiga.
     */
    public function photo(Request $request): StreamedResponse
    {
        $user = $request->user();
        $disk = Storage::disk(config('filesystems.uploads'));

        abort_unless($user->photo_path !== null && $disk->exists($user->photo_path), 404);

        return $disk->response($user->photo_path, null, [
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
