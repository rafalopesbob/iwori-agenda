<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Login com conta Google (OpenID Connect).
 *
 * Usa o mesmo client OAuth da integração com o Google Agenda, mas com outra
 * redirect URI e só os escopos básicos de identidade: não pede acesso à agenda.
 */
class GoogleLoginService
{
    protected const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    protected const ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    public const SCOPES = 'openid email profile';

    /**
     * Chave de sessão com o state e o verificador PKCE do login em andamento.
     */
    public const FLOW_SESSION_KEY = 'google_login';

    /**
     * Chave de sessão com a conta Google esperando a senha para ser vinculada.
     */
    public const PENDING_LINK_SESSION_KEY = 'google_login_link';

    /**
     * Credenciais do Google configuradas no ambiente?
     */
    public function isConfigured(): bool
    {
        return (bool) config('services.google.client_id')
            && (bool) config('services.google.client_secret');
    }

    /**
     * Mostrar o botão do Google? Precisa das credenciais, e fica fora do
     * ambiente de demonstração, onde ninguém cria conta.
     */
    public function isAvailable(): bool
    {
        return $this->isConfigured() && ! config('demo.enabled');
    }

    /**
     * URL da tela de escolha de conta do Google.
     *
     * O state protege o callback contra CSRF; o PKCE impede que um código
     * interceptado seja trocado por outra pessoa.
     */
    public function authUrl(string $state, string $codeVerifier): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => route('login.google.callback'),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
            'code_challenge' => $this->codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ]);
    }

    /**
     * Troca o código pelo ID token e devolve quem é a pessoa.
     *
     * Retorna null se o token não for deste app ou se o Google não tiver
     * confirmado o e-mail da conta.
     *
     * @return array{sub: string, email: string, name: string}|null
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public function identity(string $code, string $codeVerifier): ?array
    {
        $tokens = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('login.google.callback'),
        ])->throw()->json();

        $claims = $this->decodeIdToken($tokens['id_token'] ?? null);

        if ($claims === null || ! $this->claimsAreValid($claims)) {
            return null;
        }

        $email = Str::lower($claims['email']);

        return [
            'sub' => (string) $claims['sub'],
            'email' => $email,
            'name' => filled($claims['name'] ?? null) ? $claims['name'] : Str::before($email, '@'),
        ];
    }

    /**
     * Confere se o ID token foi emitido pelo Google para este app, ainda vale
     * e traz um e-mail que o Google confirmou.
     *
     * @param  array<string, mixed>  $claims
     */
    protected function claimsAreValid(array $claims): bool
    {
        return ($claims['aud'] ?? null) === config('services.google.client_id')
            && in_array($claims['iss'] ?? null, self::ISSUERS, true)
            && (int) ($claims['exp'] ?? 0) > now()->timestamp
            && filled($claims['sub'] ?? null)
            && filled($claims['email'] ?? null)
            && filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Lê o conteúdo do ID token.
     *
     * A assinatura não é conferida: o token veio direto do endpoint de token
     * do Google, por HTTPS e autenticado com o client secret. O Google indica
     * que, nesse caso, a origem já está garantida.
     *
     * @return array<string, mixed>|null
     */
    protected function decodeIdToken(?string $idToken): ?array
    {
        $parts = explode('.', (string) $idToken);

        if (count($parts) !== 3) {
            return null;
        }

        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($claims) ? $claims : null;
    }

    /**
     * Desafio PKCE (S256) derivado do verificador guardado na sessão.
     */
    protected function codeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }
}
