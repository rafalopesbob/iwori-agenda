<?php

namespace App\Http\Controllers;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class DemoLoginController extends Controller
{
    /**
     * Entra na conta de demonstração com um clique, sem expor a senha.
     *
     * Só existe no ambiente de demonstração (DEMO_MODE=true); fora dele, 404.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(config('demo.enabled'), 404);

        $user = User::where('email', DemoSeeder::EMAIL)->first();

        // Banco recém-criado ou conta apagada: recria a demo antes de entrar.
        if ($user === null) {
            Artisan::call('demo:reset');
            $user = User::where('email', DemoSeeder::EMAIL)->firstOrFail();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
