<?php

namespace Tests\Feature\Inventario;

use App\Models\CategoriaRepuesto;
use App\Models\Distribuidor;
use App\Models\Repuesto;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

class DistribuidoresYCategoriasTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taller = Taller::factory()->create(['nombre' => 'Servimotos Fruna']);
        $this->admin = User::factory()->administradora()->deTaller($this->taller)->create();
    }

    private function distribuidorConPedido(): array
    {
        $distribuidor = Distribuidor::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Motopartes Ejemplo',
            'contacto' => 'Carlos',
            'telefono_whatsapp' => '3001234567',
        ]);
        $repuesto = Repuesto::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Filtro de aceite K&N',
            'stock_actual' => 1,
            'stock_minimo' => 3,
            'precio_costo' => 6600,
        ]);
        $distribuidor->repuestos()->attach($repuesto->id, [
            'taller_id' => $this->taller->id,
            'codigo_proveedor' => 'KN-204',
        ]);

        return [$distribuidor, $repuesto];
    }

    public function test_crud_de_distribuidores(): void
    {
        $this->actingAs($this->admin)->get(route('distribuidores.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('distribuidores.store'), [
                'nombre' => 'Motopartes Ejemplo',
                'telefono_whatsapp' => '300 123 4567',
                'activo' => '1',
            ])
            ->assertRedirect(route('distribuidores.index'));

        $distribuidor = Distribuidor::withoutGlobalScopes()->where('nombre', 'Motopartes Ejemplo')->firstOrFail();
        $this->assertSame($this->taller->id, $distribuidor->taller_id);

        $this->actingAs($this->admin)
            ->put(route('distribuidores.update', $distribuidor), ['nombre' => 'Motopartes Ejemplo', 'activo' => '0'])
            ->assertRedirect(route('distribuidores.index'));
        $this->assertFalse($distribuidor->fresh()->activo);

        $this->actingAs($this->admin)->get(route('distribuidores.index'))
            ->assertOk()
            ->assertSee('Motopartes Ejemplo')
            ->assertSee('Inactivo');

        $this->actingAs($this->admin)
            ->post(route('distribuidores.store'), ['nombre' => 'Motopartes Ejemplo'])
            ->assertSessionHasErrors('nombre');
    }

    public function test_el_mecanico_no_accede_a_distribuidores_ni_categorias(): void
    {
        [$distribuidor] = $this->distribuidorConPedido();
        $mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();

        $this->actingAs($mecanico)->get(route('distribuidores.index'))->assertForbidden();
        $this->actingAs($mecanico)->get(route('distribuidores.solicitud', $distribuidor))->assertForbidden();
        $this->actingAs($mecanico)
            ->postJson(route('distribuidores.solicitud.whatsapp', $distribuidor), ['cantidades' => [1 => 1]])
            ->assertForbidden();
        $this->actingAs($mecanico)->get(route('categorias.index'))->assertForbidden();
    }

    public function test_pantalla_de_solicitud_muestra_cantidad_sugerida(): void
    {
        [$distribuidor, $repuesto] = $this->distribuidorConPedido();

        $this->actingAs($this->admin)->get(route('distribuidores.solicitud', $distribuidor))
            ->assertOk()
            ->assertSee('Filtro de aceite K&N')
            ->assertSee('KN-204')
            ->assertSee('\u0022cantidad\u0022:5', false)
            ->assertSee("cantidades[{$repuesto->id}]", false);
    }

    public function test_genera_pdf_de_pedido(): void
    {
        [$distribuidor, $repuesto] = $this->distribuidorConPedido();

        $this->actingAs($this->admin)
            ->post(route('distribuidores.solicitud.pdf', $distribuidor), ['cantidades' => [$repuesto->id => 5]])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_sin_cantidades_vuelve_con_error(): void
    {
        [$distribuidor, $repuesto] = $this->distribuidorConPedido();

        $this->actingAs($this->admin)
            ->from(route('distribuidores.solicitud', $distribuidor))
            ->post(route('distribuidores.solicitud.pdf', $distribuidor), ['cantidades' => [$repuesto->id => 0]])
            ->assertRedirect(route('distribuidores.solicitud', $distribuidor))
            ->assertSessionHasErrors('cantidades');
    }

    public function test_genera_texto_de_whatsapp_en_json(): void
    {
        [$distribuidor, $repuesto] = $this->distribuidorConPedido();

        $respuesta = $this->actingAs($this->admin)
            ->postJson(route('distribuidores.solicitud.whatsapp', $distribuidor), ['cantidades' => [$repuesto->id => 5]])
            ->assertOk()
            ->assertJsonPath('referencias', 1)
            ->assertJsonPath('unidades', 5);

        $this->assertStringContainsString('Hola Carlos, le saluda Servimotos Fruna.', $respuesta->json('texto'));
        $this->assertStringContainsString('Ref: KN-204 · Cant: 5', $respuesta->json('texto'));
        $this->assertStringStartsWith('https://wa.me/573001234567?text=', $respuesta->json('enlace'));

        $this->actingAs($this->admin)
            ->postJson(route('distribuidores.solicitud.whatsapp', $distribuidor), ['cantidades' => [$repuesto->id => 0]])
            ->assertStatus(422);
    }

    public function test_no_se_puede_pedir_a_un_distribuidor_de_otro_taller(): void
    {
        [$distribuidor, $repuesto] = $this->distribuidorConPedido();
        $adminAjena = User::factory()->administradora()->create();

        $this->actingAs($adminAjena)->get(route('distribuidores.solicitud', $distribuidor))->assertNotFound();
        $this->actingAs($adminAjena)
            ->postJson(route('distribuidores.solicitud.whatsapp', $distribuidor), ['cantidades' => [$repuesto->id => 1]])
            ->assertNotFound();
    }

    public function test_crud_de_categorias(): void
    {
        $this->actingAs($this->admin)->get(route('categorias.index'))
            ->assertOk()
            ->assertSee('Kit de arrastre');

        $this->actingAs($this->admin)
            ->post(route('categorias.store'), ['nombre' => 'Llantas', 'orden' => 30, 'activo' => '1'])
            ->assertRedirect(route('categorias.index'));

        $this->enTaller($this->taller);
        $llantas = CategoriaRepuesto::where('slug', 'llantas')->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('categorias.update', $llantas), ['nombre' => 'Llantas y neumáticos', 'orden' => 31, 'activo' => '0'])
            ->assertRedirect(route('categorias.index'));

        $llantas->refresh();
        $this->assertSame('Llantas y neumáticos', $llantas->nombre);
        $this->assertSame('llantas', $llantas->slug, 'El slug no cambia al renombrar.');
        $this->assertFalse($llantas->activo);

        $this->actingAs($this->admin)
            ->post(route('categorias.store'), ['nombre' => 'Frenos', 'orden' => 1])
            ->assertSessionHasErrors('nombre');
    }
}
