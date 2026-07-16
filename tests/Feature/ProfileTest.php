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
        ]);

        $this->assertNull($user->refresh()->email_verified_at);
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

    public function test_upload_de_foto_salva_no_disk_public(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/photo', [
                'photo' => UploadedFile::fake()->image('avatar.png', 200, 200),
            ])
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertNotNull($user->photo_path);
        Storage::disk('public')->assertExists($user->photo_path);
    }

    public function test_trocar_foto_apaga_arquivo_antigo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->image('primeira.png'),
        ]);
        $oldPath = $user->refresh()->photo_path;

        $this->actingAs($user)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->image('segunda.png'),
        ]);

        $user->refresh();
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($user->photo_path);
    }

    public function test_remover_foto(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->image('avatar.png'),
        ]);
        $path = $user->refresh()->photo_path;

        $this->actingAs($user)
            ->delete('/profile/photo')
            ->assertRedirect('/profile');

        $this->assertNull($user->refresh()->photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_arquivo_nao_imagem_e_rejeitado(): void
    {
        Storage::fake('public');

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
