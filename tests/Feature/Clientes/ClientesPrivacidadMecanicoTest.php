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

/**
 * El mecánico ve clientes solo por primer nombre y placa: sin cédula,
 * teléfono, correo ni tipo/descuento, y no puede buscar por cédula o teléfono.
 */
class ClientesPrivacidadMecanicoTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private const SENSIBLES = ['Gómez', 'Rojas', '1020304050', '3001234567', '+573001234567', 'andres@correo.co', 'VIP', '−10%'];

    private Taller $taller;

    private User $mecanico;

    private User $admin;

    private Cliente $andres;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taller = Taller::factory()->create();
        $this->mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();
        $this->admin = User::factory()->administradora()->deTaller($this->taller)->create();

        $this->enTaller($this->taller);
        $this->andres = Cliente::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Andrés Gómez Rojas',
            'cedula_nit' => '1020304050',
            'telefono' => '3001234567',
            'correo' => 'andres@correo.co',
            'tipo_cliente_id' => TipoCliente::where('slug', 'vip-flota')->value('id'),
        ]);
        Moto::factory()->create(['cliente_id' => $this->andres->id, 'placa' => 'ABC12D', 'marca' => 'Bajaj', 'modelo' => 'Pulsar']);
        $this->enTaller(null);
    }

    private function assertSinDatosPrivados($respuesta): void
    {
        foreach (self::SENSIBLES as $sensible) {
            $respuesta->assertDontSee($sensible, false);
        }
    }

    public function test_listado_muestra_primer_nombre_y_placa_sin_contacto(): void
    {
        $respuesta = $this->actingAs($this->mecanico)->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('Andrés')
            ->assertSee('ABC12D')
            ->assertSee('Nombre o placa');

        $this->assertSinDatosPrivados($respuesta);
    }

    public function test_ficha_sin_contacto_ni_tipo(): void
    {
        $respuesta = $this->actingAs($this->mecanico)->get(route('clientes.show', $this->andres))
            ->assertOk()
            ->assertSee('Andrés')
            ->assertSee('ABC12D');

        $this->assertSinDatosPrivados($respuesta);
    }

    public function test_el_mecanico_busca_por_nombre_o_placa(): void
    {
        foreach (['andrés', 'abc12d'] as $termino) {
            $this->actingAs($this->mecanico)->get(route('clientes.index', ['buscar' => $termino]))
                ->assertOk()
                ->assertViewHas('clientes', fn ($clientes) => $clientes->pluck('id')->all() === [$this->andres->id]);

            $this->actingAs($this->mecanico)->getJson(route('clientes.buscar', ['q' => $termino]))
                ->assertOk()
                ->assertJsonCount(1, 'data');
        }
    }

    public function test_el_mecanico_no_puede_buscar_por_cedula_ni_telefono(): void
    {
        foreach (['1020304050', '300 123 4567', 'Gómez Rojas'] as $termino) {
            $this->actingAs($this->mecanico)->getJson(route('clientes.buscar', ['q' => $termino]))
                ->assertOk()
                ->assertJsonCount($termino === 'Gómez Rojas' ? 1 : 0, 'data');
        }

        $this->actingAs($this->mecanico)->get(route('clientes.index', ['buscar' => '1020304050']))
            ->assertOk()
            ->assertViewHas('clientes', fn ($clientes) => $clientes->isEmpty());
    }

    public function test_el_buscador_json_no_trae_datos_privados(): void
    {
        $respuesta = $this->actingAs($this->mecanico)->getJson(route('clientes.buscar', ['q' => 'abc12d']))
            ->assertOk()
            ->assertExactJson(['data' => [[
                'id' => $this->andres->id,
                'nombre' => 'Andrés',
                'estado_cliente' => 'activo',
                'motos' => [[
                    'id' => $this->andres->motos()->value('id'),
                    'placa' => 'ABC12D',
                    'marca' => 'Bajaj',
                    'modelo' => 'Pulsar',
                    'anio' => $this->andres->motos()->value('anio'),
                ]],
            ]]]);

        $this->assertSinDatosPrivados($respuesta);
    }

    public function test_el_modal_de_crear_orden_le_ofrece_buscar_por_nombre_o_placa(): void
    {
        $this->actingAs($this->mecanico)->get(route('ordenes.kanban'))
            ->assertOk()
            ->assertSee('placeholder="Nombre o placa"', false);
    }

    public function test_la_administradora_sigue_viendo_y_buscando_todo(): void
    {
        $this->actingAs($this->admin)->get(route('clientes.show', $this->andres))
            ->assertOk()
            ->assertSee('Andrés Gómez Rojas')
            ->assertSee('1020304050')
            ->assertSee('andres@correo.co')
            ->assertSee('VIP');

        $this->actingAs($this->admin)->getJson(route('clientes.buscar', ['q' => '300 123']))
            ->assertOk()
            ->assertJsonPath('data.0.nombre', 'Andrés Gómez Rojas')
            ->assertJsonPath('data.0.cedula_nit', '1020304050');
    }
}
