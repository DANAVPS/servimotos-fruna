<?php

namespace Tests\Feature\Clientes;

use App\Models\Cliente;
use App\Models\Moto;
use App\Models\Taller;
use App\Models\TipoCliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

class ClienteBusquedaYPermisosTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private Cliente $andres;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taller = Taller::factory()->create();
        $this->enTaller($this->taller);

        $this->andres = Cliente::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Andrés Gómez',
            'telefono' => '3001234567',
            'cedula_nit' => '1020304050',
        ]);
        Moto::factory()->create(['cliente_id' => $this->andres->id, 'placa' => 'ABC12D', 'marca' => 'Bajaj', 'modelo' => 'Pulsar NS 200']);

        $flota = Cliente::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Mensajería Rápida SAS',
            'telefono' => '3109876543',
            'cedula_nit' => '900123456-1',
        ]);
        Moto::factory()->create(['cliente_id' => $flota->id, 'placa' => 'XYZ98', 'marca' => 'AKT', 'modelo' => 'NKD 125']);

        Cliente::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Andrea Archivada',
            'telefono' => '3150000000',
            'estado_cliente' => 'archivado',
        ]);
    }

    private function nombres(string $termino): array
    {
        return Cliente::buscar($termino)->orderBy('nombre')->pluck('nombre')->all();
    }

    public function test_busca_por_nombre_telefono_cedula_nit_placa_marca_y_modelo(): void
    {
        $this->assertSame(['Andrés Gómez'], $this->nombres('andrés'));
        $this->assertSame(['Andrés Gómez'], $this->nombres('300 123'));
        $this->assertSame(['Andrés Gómez'], $this->nombres('1.020.304'));
        $this->assertSame(['Mensajería Rápida SAS'], $this->nombres('900123456'));
        $this->assertSame(['Andrés Gómez'], $this->nombres('abc-12d'));
        $this->assertSame(['Mensajería Rápida SAS'], $this->nombres('xyz98'));
        $this->assertSame(['Mensajería Rápida SAS'], $this->nombres('akt'));
        $this->assertSame(['Andrés Gómez'], $this->nombres('pulsar'));
    }

    public function test_los_archivados_no_aparecen_en_la_busqueda_por_defecto(): void
    {
        $this->assertSame(['Andrés Gómez'], $this->nombres('andr'));
    }

    public function test_un_termino_que_se_normaliza_a_vacio_no_trae_todos(): void
    {
        $this->assertSame([], $this->nombres('...'));
    }

    public function test_buscador_json_para_el_mecanico(): void
    {
        $mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();
        $this->enTaller(null);

        $this->actingAs($mecanico)->getJson(route('clientes.buscar', ['q' => 'abc12d']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            // Al mecánico: primer nombre y motos; sin contacto ni tipo (descuento).
            ->assertJsonPath('data.0.nombre', 'Andrés')
            ->assertJsonPath('data.0.motos.0.placa', 'ABC12D')
            ->assertJsonMissingPath('data.0.cedula_nit')
            ->assertJsonMissingPath('data.0.telefono')
            ->assertJsonMissingPath('data.0.tipo_cliente');
    }

    public function test_el_mecanico_ve_clientes_pero_no_puede_crear_ni_editar(): void
    {
        $mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();
        $this->enTaller(null);

        $this->actingAs($mecanico)->get(route('clientes.index'))->assertOk();
        $this->actingAs($mecanico)->get(route('clientes.show', $this->andres))->assertOk();

        $this->actingAs($mecanico)->get(route('clientes.create'))->assertForbidden();
        $this->actingAs($mecanico)->post(route('clientes.store'), ['nombre' => 'X'])->assertForbidden();
        $this->actingAs($mecanico)->get(route('clientes.edit', $this->andres))->assertForbidden();
        $this->actingAs($mecanico)
            ->put(route('clientes.update', $this->andres), [
                'nombre' => 'Hackeado',
                'telefono' => '3001234567',
                'estado_cliente' => 'archivado',
            ])
            ->assertForbidden();

        $this->assertSame('Andrés Gómez', $this->andres->fresh()->nombre);
    }

    public function test_el_mecanico_no_gestiona_tipos_de_cliente(): void
    {
        $mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();
        $vip = TipoCliente::where('slug', 'vip-flota')->first();
        $this->enTaller(null);

        $this->actingAs($mecanico)->get(route('tipos-cliente.index'))->assertForbidden();
        $this->actingAs($mecanico)->post(route('tipos-cliente.store'), ['nombre' => 'Amigos', 'descuento_repuestos_pct' => 50])->assertForbidden();
        $this->actingAs($mecanico)
            ->put(route('tipos-cliente.update', $vip), ['nombre' => 'VIP', 'descuento_repuestos_pct' => 90, 'activo' => 1])
            ->assertForbidden();

        $this->assertEquals(10, $vip->fresh()->descuento_repuestos_pct);
    }

    public function test_un_taller_no_ve_clientes_de_otro(): void
    {
        $adminB = User::factory()->administradora()->conPin()->create();
        $this->enTaller(null);

        $this->actingAs($adminB)->get(route('clientes.index'))
            ->assertOk()
            ->assertDontSee('Andrés Gómez');
        $this->actingAs($adminB)->getJson(route('clientes.buscar', ['q' => 'andrés']))
            ->assertJsonCount(0, 'data');
        $this->actingAs($adminB)->get(route('clientes.show', $this->andres))->assertNotFound();
        $this->actingAs($adminB)->get(route('clientes.edit', $this->andres))->assertNotFound();

        $this->enTaller($adminB->taller);
        $this->assertSame([], $this->nombres('andrés'));
    }
}
