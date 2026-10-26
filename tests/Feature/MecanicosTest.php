<?php

namespace Tests\Feature;

use App\Models\Mecanico;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

class MecanicosTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private User $admin;

    private Mecanico $carlos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taller = Taller::factory()->create();
        $this->admin = User::factory()->administradora()->deTaller($this->taller)->create();
        $this->carlos = $this->mecanico(['nombre' => 'Carlos']);
    }

    private function mecanico(array $atributos, ?Taller $taller = null): Mecanico
    {
        $this->enTaller($taller ?? $this->taller);
        $mecanico = Mecanico::create($atributos);
        $this->enTaller(null);

        return $mecanico;
    }

    private function guardar(array $datos, ?Mecanico $mecanico = null)
    {
        $datos = ['nombre' => 'Pedro', 'tipo' => 'ayudante', 'activo' => '1', ...$datos];

        return $mecanico
            ? $this->actingAs($this->admin)->put(route('mecanicos.update', $mecanico), $datos)
            : $this->actingAs($this->admin)->post(route('mecanicos.store'), $datos);
    }

    public function test_listado_solo_para_administrativos(): void
    {
        $this->actingAs($this->admin)->get(route('mecanicos.index'))->assertOk()->assertSee('Carlos')->assertSee('Titular');

        $mecanicoUser = User::factory()->mecanico()->deTaller($this->taller)->create();
        $this->actingAs($mecanicoUser)->get(route('mecanicos.index'))->assertForbidden();
        $this->actingAs($mecanicoUser)->post(route('mecanicos.store'), ['nombre' => 'X', 'tipo' => 'titular'])->assertForbidden();
    }

    public function test_crea_un_ayudante_con_su_titular(): void
    {
        $this->guardar(['mecanico_principal_id' => $this->carlos->id, 'telefono' => '300 123 4567'])
            ->assertRedirect(route('mecanicos.index'));

        $pedro = Mecanico::withoutGlobalScopes()->where('nombre', 'Pedro')->sole();
        $this->assertSame('ayudante', $pedro->tipo);
        $this->assertSame($this->carlos->id, $pedro->mecanico_principal_id);
        $this->assertSame('+573001234567', $pedro->telefono);
        $this->assertSame($this->taller->id, $pedro->taller_id);
    }

    public function test_un_titular_nunca_queda_con_principal(): void
    {
        $this->guardar(['tipo' => 'titular', 'mecanico_principal_id' => $this->carlos->id])->assertSessionHasNoErrors();

        $this->assertNull(Mecanico::withoutGlobalScopes()->where('nombre', 'Pedro')->sole()->mecanico_principal_id);
    }

    public function test_nadie_es_su_propio_principal(): void
    {
        $ayudante = $this->mecanico(['nombre' => 'Pedro', 'tipo' => 'ayudante']);

        $this->guardar(['mecanico_principal_id' => $ayudante->id], $ayudante)
            ->assertSessionHasErrors(['mecanico_principal_id' => 'Un mecánico no puede ser su propio principal.']);
    }

    public function test_el_principal_debe_ser_titular_activo_del_taller(): void
    {
        $inactivo = $this->mecanico(['nombre' => 'Inactivo', 'activo' => false]);
        $ayudante = $this->mecanico(['nombre' => 'Otro ayudante', 'tipo' => 'ayudante']);
        $ajeno = $this->mecanico(['nombre' => 'Ajeno'], Taller::factory()->create());

        foreach ([$inactivo, $ayudante, $ajeno] as $candidato) {
            $this->guardar(['mecanico_principal_id' => $candidato->id])
                ->assertSessionHasErrors(['mecanico_principal_id' => 'El principal debe ser un titular activo del taller.']);
        }
    }

    public function test_un_titular_con_ayudantes_no_pasa_a_ayudante(): void
    {
        $this->mecanico(['nombre' => 'Pedro', 'tipo' => 'ayudante', 'mecanico_principal_id' => $this->carlos->id]);

        $this->guardar(['nombre' => 'Carlos', 'tipo' => 'ayudante'], $this->carlos)
            ->assertSessionHasErrors('tipo');
        $this->assertSame('titular', $this->carlos->fresh()->tipo);
    }

    public function test_desactivar_un_mecanico(): void
    {
        $this->guardar(['nombre' => 'Carlos', 'tipo' => 'titular', 'activo' => '0'], $this->carlos)
            ->assertRedirect(route('mecanicos.index'));

        $this->assertFalse($this->carlos->fresh()->activo);
    }

    public function test_el_formulario_solo_ofrece_titulares_activos(): void
    {
        $this->mecanico(['nombre' => 'Titular Inactivo', 'activo' => false]);
        $this->mecanico(['nombre' => 'Un Ayudante', 'tipo' => 'ayudante']);

        $this->actingAs($this->admin)->get(route('mecanicos.create'))
            ->assertOk()
            ->assertSee('Carlos')
            ->assertDontSee('Titular Inactivo')
            ->assertDontSee('Un Ayudante');
    }

    public function test_un_mecanico_de_otro_taller_da_404(): void
    {
        $ajeno = $this->mecanico(['nombre' => 'Ajeno'], Taller::factory()->create());

        $this->actingAs($this->admin)->get(route('mecanicos.edit', $ajeno))->assertNotFound();
    }

    // --- Vínculo con el usuario de "Mi día" ---

    public function test_vincula_un_usuario_mecanico(): void
    {
        $usuario = User::factory()->mecanico()->deTaller($this->taller)->create();

        $this->guardar(['nombre' => 'Carlos', 'tipo' => 'titular', 'user_id' => $usuario->id], $this->carlos)
            ->assertSessionHasNoErrors();

        $this->assertSame($usuario->id, $this->carlos->fresh()->user_id);
        $this->assertSame($this->carlos->id, $usuario->fresh()->mecanico->id);
    }

    public function test_un_usuario_solo_se_vincula_a_un_mecanico(): void
    {
        $usuario = User::factory()->mecanico()->deTaller($this->taller)->create();
        $this->mecanico(['nombre' => 'Pedro', 'user_id' => $usuario->id]);

        $this->guardar(['nombre' => 'Carlos', 'tipo' => 'titular', 'user_id' => $usuario->id], $this->carlos)
            ->assertSessionHasErrors(['user_id' => 'Ese usuario ya está vinculado a otro mecánico.']);
    }

    public function test_solo_usuarios_mecanicos_del_mismo_taller(): void
    {
        $otraAdmin = User::factory()->administradora()->deTaller($this->taller)->create();
        $deOtroTaller = User::factory()->mecanico()->deTaller(Taller::factory()->create())->create();

        foreach ([$otraAdmin, $deOtroTaller] as $usuario) {
            $this->guardar(['nombre' => 'Carlos', 'tipo' => 'titular', 'user_id' => $usuario->id], $this->carlos)
                ->assertSessionHasErrors(['user_id' => 'El usuario debe tener rol mecánico en este taller.']);
        }
    }

    public function test_el_formulario_ofrece_solo_usuarios_libres(): void
    {
        $libre = User::factory()->mecanico()->deTaller($this->taller)->create(['email' => 'libre@taller.co']);
        $ocupado = User::factory()->mecanico()->deTaller($this->taller)->create(['email' => 'ocupado@taller.co']);
        $this->mecanico(['nombre' => 'Pedro', 'user_id' => $ocupado->id]);

        $this->actingAs($this->admin)->get(route('mecanicos.create'))
            ->assertOk()
            ->assertSee('libre@taller.co')
            ->assertDontSee('ocupado@taller.co');
    }
}
