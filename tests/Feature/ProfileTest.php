<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_tela_de_perfil_carrega(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Meu perfil')
            ->assertSee($user->email);
    }

    public function test_visitante_nao_acessa_perfil(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_atualiza_nome_e_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Novo Nome',
                'email' => 'novo@teste.com',
                'email_current_password' => 'password',
            ])
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('Novo Nome', $user->name);
        $this->assertSame('novo@teste.com', $user->email);
    }

    public function test_trocar_email_anula_verificacao(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'outro@teste.com',
            'email_current_password' => 'password',
        ]);

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_trocar_email_sem_senha_atual_e_rejeitado(): void
    {
        $user = User::factory()->create(['email' => 'original@teste.com']);

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'invasor@teste.com',
            ])
            ->assertSessionHasErrors('email_current_password');

        $this->assertSame('original@teste.com', $user->refresh()->email);
    }

    public function test_trocar_email_com_senha_errada_e_rejeitado(): void
    {
        $user = User::factory()->create(['email' => 'original@teste.com']);

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'invasor@teste.com',
                'email_current_password' => 'senha-errada',
            ])
            ->assertSessionHasErrors('email_current_password');

        $this->assertSame('original@teste.com', $user->refresh()->email);
    }

    public function test_trocar_so_o_nome_nao_pede_senha(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', ['name' => 'Outro Nome', 'email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('Outro Nome', $user->refresh()->name);
    }

    public function test_manter_email_preserva_verificacao(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Só o nome muda',
            'email' => $user->email,
        ]);

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_email_duplicado_e_rejeitado(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'ocupado@teste.com']);

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'ocupado@teste.com',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_alterar_senha_exige_senha_atual_correta(): void
    {
        $user = User::factory()->create(['password' => 'Senha-Atual-123!']);

        $this->actingAs($user)
            ->from('/profile')
            ->put('/profile/password', [
                'current_password' => 'senha-errada',
                'password' => 'Nova-Senha-Forte-456!',
                'password_confirmation' => 'Nova-Senha-Forte-456!',
            ])
            ->assertSessionHasErrorsIn('password', ['current_password']);

        $this->assertTrue(Hash::check('Senha-Atual-123!', $user->refresh()->password));
    }

    public function test_alterar_senha_com_dados_validos(): void
    {
        $user = User::factory()->create(['password' => 'Senha-Atual-123!']);

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'Senha-Atual-123!',
                'password' => 'Nova-Senha-Forte-456!',
                'password_confirmation' => 'Nova-Senha-Forte-456!',
            ])
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('Nova-Senha-Forte-456!', $user->refresh()->password));
    }

    public function test_trocar_senha_mantem_a_sessao_atual_logada(): void
    {
        $user = User::factory()->create(['password' => 'Senha-Atual-123!']);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->put('/profile/password', [
            'current_password' => 'Senha-Atual-123!',
            'password' => 'Nova-Senha-Forte-456!',
            'password_confirmation' => 'Nova-Senha-Forte-456!',
        ])->assertRedirect('/profile');

        $this->get('/dashboard')->assertOk();
    }

    public function test_sessao_aberta_antes_da_troca_de_senha_e_derrubada(): void
    {
        $user = User::factory()->create(['password' => 'Senha-Atual-123!']);

        // Simula outra sessão (ex.: outro computador) que guardou o hash da senha antiga.
        $hashAntigo = auth()->guard('web')->hashPasswordForCookie($user->password);
        $user->update(['password' => 'Nova-Senha-Forte-456!']);

        $this->actingAs($user)
            ->withSession(['password_hash_web' => $hashAntigo])
            ->get('/dashboard')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_nova_senha_respeita_politica_forte(): void
    {
        $user = User::factory()->create(['password' => 'Senha-Atual-123!']);

        $this->actingAs($user)
            ->from('/profile')
            ->put('/profile/password', [
                'current_password' => 'Senha-Atual-123!',
                'password' => '123456',
                'password_confirmation' => '123456',
            ])
            ->assertSessionHasErrorsIn('password', ['password']);
    }

    public function test_upload_de_foto_salva_no_disco_privado_de_uploads(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/photo', [
                'photo' => UploadedFile::fake()->image('avatar.png', 200, 200),
            ])
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertNotNull($user->photo_path);
        $this->assertStringStartsWith("avatars/{$user->id}/", $user->photo_path);
        Storage::disk('local')->assertExists($user->photo_path);
    }

    public function test_upload_de_foto_usa_o_disco_configurado(): void
    {
        config(['filesystems.uploads' => 'r2']);
        Storage::fake('r2');
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->image('avatar.png'),
        ]);

        $path = $user->refresh()->photo_path;
        Storage::disk('r2')->assertExists($path);
        Storage::disk('local')->assertMissing($path);

        $this->actingAs($user)->get($user->photoUrl())->assertOk();
    }

    public function test_trocar_foto_apaga_arquivo_antigo(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->image('primeira.png'),
        ]);
        $oldPath = $user->refresh()->photo_path;
        $oldUrl = $user->photoUrl();

        $this->actingAs($user)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->image('segunda.png'),
        ]);

        $user->refresh();
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($user->photo_path);
        // URL nova a cada foto: o cache do navegador nunca mostra a antiga.
        $this->assertNotSame($oldUrl, $user->photoUrl());
    }

    public function test_remover_foto(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $this->actingAs($user)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->image('avatar.png'),
        ]);
        $path = $user->refresh()->photo_path;

        $this->actingAs($user)
            ->delete('/profile/photo')
            ->assertRedirect('/profile');

        $this->assertNull($user->refresh()->photo_path);
        $this->assertNull($user->photoUrl());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_foto_e_entregue_so_para_o_dono_pela_rota_autenticada(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $this->actingAs($user)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->image('avatar.png'),
        ]);
        $user->refresh();

        $this->assertStringContainsString('/profile/photo?v=', $user->photoUrl());
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee($user->photoUrl(), false);

        $response = $this->actingAs($user)->get($user->photoUrl())->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));

        // Outro profissional recebe só a própria foto (aqui, nenhuma): nunca a de outra pessoa.
        $this->actingAs(User::factory()->create())->get('/profile/photo')->assertNotFound();
    }

    public function test_foto_exige_login(): void
    {
        $this->get('/profile/photo')->assertRedirect('/login');
    }

    public function test_foto_que_sumiu_do_disco_responde_404(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->forceFill(['photo_path' => "avatars/{$user->id}/apagada.png"])->save();

        $this->actingAs($user)->get('/profile/photo')->assertNotFound();
    }

    public function test_arquivo_nao_imagem_e_rejeitado(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile')
            ->post('/profile/photo', [
                'photo' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrorsIn('photo', ['photo']);

        $this->assertNull($user->refresh()->photo_path);
    }
}
