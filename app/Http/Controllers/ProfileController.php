<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfilePhotoRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Exibe a tela de edição do perfil do profissional autenticado.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
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
     * Altera a senha do profissional.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        // Novo ID de sessão; as demais sessões da conta caem pelo middleware auth.session.
        $request->session()->regenerate();

        return redirect()->route('profile.edit')
            ->with('status', 'Senha alterada com sucesso.');
    }

    /**
     * Envia ou substitui a foto de perfil.
     */
    public function updatePhoto(UpdateProfilePhotoRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->photo_path !== null) {
            Storage::disk('public')->delete($user->photo_path);
        }

        $user->forceFill([
            'photo_path' => $request->file('photo')->store('avatars', 'public'),
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
            Storage::disk('public')->delete($user->photo_path);
            $user->forceFill(['photo_path' => null])->save();
        }

        return redirect()->route('profile.edit')
            ->with('status', 'Foto de perfil removida.');
    }
}
