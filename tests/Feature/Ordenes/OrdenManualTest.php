<?php

namespace Tests\Feature\Ordenes;

use App\Models\Cliente;
use App\Models\Moto;
use App\Models\OrdenEstadoHistorial;
use App\Models\OrdenServicio;
use App\Models\Taller;
use App\Models\TipoCliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

/**
 * "+ Crear orden manual" del Kanban (POST /ordenes).
 */
class OrdenManualTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private User $mecanico;

    private Cliente $andres;

    private Moto $pulsar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taller = Taller::factory()->create();
        $this->mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();

        $this->enTaller($this->taller);
        $vip = TipoCliente::where('slug', 'vip-flota')->firstOrFail();
        $this->andres = Cliente::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Andrés Gómez',
            'tipo_cliente_id' => $vip->id,
            'estado_cliente' => 'hibernando',
            'fecha_ultima_visita' => now()->subMonths(7),
        ]);
        $this->pulsar = Moto::factory()->create(['cliente_id' => $this->andres->id, 'placa' => 'ABC12D']);
        $this->enTaller(null);
    }

    private function crear(array $datos, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->mecanico)->postJson(route('ordenes.store'), $datos);
    }

    private function historial(int $ordenId): OrdenEstadoHistorial
    {
        return OrdenEstadoHistorial::withoutGlobalScopes()->where('orden_servicio_id', $ordenId)->sole();
    }

    public function test_cliente_y_moto_existentes(): void
    {
        $respuesta = $this->crear([
            'cliente_id' => $this->andres->id,
            'moto_id' => $this->pulsar->id,
            'descripcion_falla' => 'No prende',
        ])->assertCreated()
            ->assertJsonPath('orden.estado', 'en_espera_revision')
            ->assertJsonPath('orden.placa', 'ABC12D')
            ->assertJsonPath('orden.cliente', 'Andrés'); // el mecánico solo ve el primer nombre

        $orden = OrdenServicio::withoutGlobalScopes()->findOrFail($respuesta->json('orden.id'));
        $this->assertSame($this->taller->id, $orden->taller_id);
        $this->assertSame('No prende', $orden->descripcion_falla);

        $fila = $this->historial($orden->id);
        $this->assertSame('creacion', $fila->evento);
        $this->assertNull($fila->estado_anterior);
        $this->assertSame('en_espera_revision', $fila->estado_nuevo);
        $this->assertSame($this->mecanico->id, $fila->user_id);
    }

    public function test_el_estado_inicial_es_forzado(): void
    {
        $respuesta = $this->crear([
            'cliente_id' => $this->andres->id,
            'moto_id' => $this->pulsar->id,
            'estado' => 'pagada',
            'total' => 1,
        ])->assertCreated();

        $orden = OrdenServicio::withoutGlobalScopes()->findOrFail($respuesta->json('orden.id'));
        $this->assertSame('en_espera_revision', $orden->estado);
        $this->assertEquals(0, $orden->total);
    }

    public function test_la_visita_reactiva_a_un_cliente_hibernando(): void
    {
        $this->crear(['cliente_id' => $this->andres->id, 'moto_id' => $this->pulsar->id])->assertCreated();

        $andres = Cliente::withoutGlobalScopes()->find($this->andres->id);
        $this->assertSame('activo', $andres->estado_cliente);
        $this->assertTrue($andres->fecha_ultima_visita->isToday());
    }

    public function test_cliente_existente_con_moto_nueva_sin_pin(): void
    {
        $respuesta = $this->crear([
            'cliente_id' => $this->andres->id,
            'moto' => ['placa' => 'xyz-98e', 'marca' => 'AKT', 'modelo' => 'NKD 125'],
        ])->assertCreated()->assertJsonPath('orden.placa', 'XYZ98E');

        $this->assertSame(2, Moto::withoutGlobalScopes()->where('cliente_id', $this->andres->id)->count());
        $this->assertSame(
            'Moto nueva registrada en la recepción: XYZ98E.',
            $this->historial($respuesta->json('orden.id'))->observaciones
        );
    }

    public function test_el_mecanico_crea_cliente_nuevo_siempre_regular(): void
    {
        $vip = TipoCliente::withoutGlobalScopes()->where('taller_id', $this->taller->id)->where('slug', 'vip-flota')->first();

        $respuesta = $this->crear([
            'cliente' => [
                'nombre' => 'Laura Pérez',
                'telefono' => '310 555 1234',
                'cedula_nit' => '1.098.765.432',
                'tipo_cliente_id' => $vip->id,
            ],
            'moto' => ['placa' => 'QWE45R', 'marca' => 'Yamaha', 'modelo' => 'FZ 16'],
        ])->assertCreated()->assertJsonPath('orden.cliente', 'Laura');

        $laura = Cliente::withoutGlobalScopes()->where('nombre', 'Laura Pérez')->sole();
        $this->assertSame('regular', $laura->tipoClienteEfectivo()->slug);
        $this->assertSame('+573105551234', $laura->telefono);
        $this->assertSame($this->taller->id, $laura->taller_id);
        $this->assertSame(
            'Cliente nuevo registrado en la recepción (tipo Regular).',
            $this->historial($respuesta->json('orden.id'))->observaciones
        );
    }

    public function test_placa_de_otro_cliente_dice_a_nombre_de_quien_esta(): void
    {
        $this->crear([
            'cliente' => ['nombre' => 'Laura Pérez', 'telefono' => '3105551234'],
            'moto' => ['placa' => 'ABC12D', 'marca' => 'Bajaj', 'modelo' => 'Pulsar'],
        ])->assertJsonValidationErrors(['moto.placa' => 'Esta moto ya está registrada a nombre de Andrés Gómez.']);

        $this->assertSame(0, Cliente::withoutGlobalScopes()->where('nombre', 'Laura Pérez')->count());
    }

    public function test_moto_de_otro_cliente_se_rechaza(): void
    {
        $this->enTaller($this->taller);
        $otro = Cliente::factory()->create(['taller_id' => $this->taller->id]);
        $this->enTaller(null);

        $this->crear(['cliente_id' => $otro->id, 'moto_id' => $this->pulsar->id])
            ->assertJsonValidationErrors(['moto_id' => 'La moto no pertenece a este cliente.']);
    }

    public function test_cliente_archivado_no_se_puede_elegir(): void
    {
        Cliente::withoutGlobalScopes()->whereKey($this->andres->id)->update(['estado_cliente' => 'archivado']);

        $this->crear(['cliente_id' => $this->andres->id, 'moto_id' => $this->pulsar->id])
            ->assertJsonValidationErrors('cliente_id');
    }

    public function test_cliente_de_otro_taller_se_rechaza(): void
    {
        $otroTaller = Taller::factory()->create();
        $ajeno = Cliente::factory()->create(['taller_id' => $otroTaller->id]);

        $this->crear(['cliente_id' => $ajeno->id, 'moto' => ['placa' => 'JKL11', 'marca' => 'AKT', 'modelo' => 'X']])
            ->assertJsonValidationErrors('cliente_id');
    }

    public function test_sin_cliente_ni_moto_explica_que_falta(): void
    {
        $this->crear([])->assertJsonValidationErrors(['cliente', 'moto']);
    }

    public function test_superadmin_sin_taller_no_crea(): void
    {
        $this->crear(['cliente_id' => $this->andres->id, 'moto_id' => $this->pulsar->id], User::factory()->superAdmin()->create())
            ->assertForbidden();
    }
}
