<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Mecanico;
use App\Models\Moto;
use App\Models\OrdenServicio;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

/**
 * "Mi día": solo las órdenes del mecánico, sin dinero ni datos de contacto.
 */
class MiDiaTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private User $usuario;

    private Mecanico $alexander;

    private Mecanico $ferney;

    private int $clientes = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taller = Taller::factory()->create();
        $this->usuario = User::factory()->mecanico()->deTaller($this->taller)->create(['name' => 'Usuario Taller']);

        $this->enTaller($this->taller);
        $this->alexander = Mecanico::create(['nombre' => 'Alexánder Duarte', 'user_id' => $this->usuario->id]);
        $this->ferney = Mecanico::create(['nombre' => 'Ferney Cárdenas']);
        $this->enTaller(null);
    }

    private function orden(string $placa, string $estado, ?Mecanico $mecanico, int $dias = 0, array $atributos = []): OrdenServicio
    {
        $this->enTaller($this->taller);
        // El primero lleva los datos fijos que el test de datos sensibles busca.
        $n = $this->clientes++;
        $cliente = Cliente::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Juan Pérez Gómez',
            'cedula_nit' => (string) (1020304050 + $n),
            'telefono' => (string) (3001234567 + $n),
        ]);
        $moto = Moto::factory()->create(['cliente_id' => $cliente->id, 'placa' => $placa, 'marca' => 'Yamaha', 'modelo' => 'FZ 16']);
        $orden = OrdenServicio::create([
            'cliente_id' => $cliente->id,
            'moto_id' => $moto->id,
            'mecanico_id' => $mecanico?->id,
            'estado' => $estado,
            'fecha_hora_ingreso' => now()->subDays($dias),
            ...$atributos,
        ]);
        $this->enTaller(null);

        return $orden;
    }

    public function test_muestra_solo_las_ordenes_activas_del_mecanico(): void
    {
        $this->orden('MIA11A', 'en_revision', $this->alexander, 6, ['descripcion_falla' => 'Ruido en el motor al acelerar']);
        $this->orden('MIA22B', 'reparacion', $this->alexander, 1);
        $this->orden('PAG33C', 'pagada', $this->alexander);
        $this->orden('LIS44D', 'listo_para_reclamar', $this->alexander);
        $this->orden('OTR55E', 'en_revision', $this->ferney);
        $this->orden('SIN66F', 'en_espera_revision', null);

        $this->actingAs($this->usuario)->get(route('mi-dia'))
            ->assertOk()
            ->assertSee('Hola Alexánder.')
            ->assertSee('Tienes 2 órdenes activas.')
            ->assertSee('MIA11A')
            ->assertSee('MIA22B')
            ->assertSee('Ruido en el motor al acelerar')
            ->assertDontSee('PAG33C')
            ->assertDontSee('LIS44D')
            ->assertDontSee('OTR55E')
            ->assertDontSee('SIN66F');
    }

    public function test_no_muestra_dinero_ni_datos_de_contacto(): void
    {
        $this->orden('MIA11A', 'terminada', $this->alexander, 2, [
            'total' => 98765,
            'mano_obra' => 45678,
            'subtotal_repuestos' => 53087,
        ]);

        $respuesta = $this->actingAs($this->usuario)->get(route('mi-dia'))->assertOk();

        $respuesta->assertSee('Moto de Juan');
        foreach (['Pérez', 'Gómez', '1020304050', '3001234567', '98.765', '98765', '45.678', '53.087', 'Total', 'Precio', 'metodo_pago', 'Método'] as $sensible) {
            $respuesta->assertDontSee($sensible);
        }
    }

    public function test_las_ordenes_en_rojo_aparecen_en_la_alerta(): void
    {
        $this->orden('ROJ11A', 'reparacion', $this->alexander, 9);
        $this->orden('VER22B', 'reparacion', $this->alexander, 0);

        $this->actingAs($this->usuario)->get(route('mi-dia'))
            ->assertOk()
            ->assertSee('1 moto lleva más de 5 días en el taller')
            ->assertViewHas('enRojo', fn ($rojas) => $rojas->pluck('placa')->all() === ['ROJ11A']);
    }

    public function test_cuenta_por_estado_y_las_terminadas_desde_el_lunes(): void
    {
        $this->orden('ESP11A', 'en_espera_revision', $this->alexander);
        $estaSemana = $this->orden('TER22B', 'terminada', $this->alexander);
        $semanaPasada = $this->orden('TER33C', 'terminada', $this->alexander);
        $deOtro = $this->orden('TER44D', 'terminada', $this->ferney);

        $estaSemana->registrarHistorial('cambio_estado', 'reparacion', $this->usuario);
        $deOtro->registrarHistorial('cambio_estado', 'reparacion', $this->usuario);
        DB::table('orden_estado_historial')->insert([
            'taller_id' => $this->taller->id,
            'orden_servicio_id' => $semanaPasada->id,
            'estado_anterior' => 'reparacion',
            'estado_nuevo' => 'terminada',
            'evento' => 'cambio_estado',
            'created_at' => now('America/Bogota')->startOfWeek()->subDay()->setTimezone(config('app.timezone')),
        ]);

        $this->actingAs($this->usuario)->get(route('mi-dia'))
            ->assertOk()
            ->assertSee('Esta semana terminaste')
            ->assertViewHas('terminadasSemana', 1)
            ->assertViewHas('porEstado', fn ($grupos) => $grupos['en_espera_revision']->count() === 1
                && $grupos['terminada']->count() === 2
                && $grupos['reparacion']->isEmpty());
    }

    public function test_usuario_sin_mecanico_vinculado_ve_el_aviso(): void
    {
        $sinVincular = User::factory()->mecanico()->deTaller($this->taller)->create(['name' => 'Pedro Gómez']);

        $this->actingAs($sinVincular)->get(route('mi-dia'))
            ->assertOk()
            ->assertSee('Tu usuario no está vinculado a un mecánico, contacta a la administradora.');
    }

    public function test_solo_entra_el_rol_mecanico(): void
    {
        $this->actingAs(User::factory()->administradora()->deTaller($this->taller)->create())
            ->get(route('mi-dia'))->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('mi-dia'))->assertForbidden();

        auth()->logout();
        $this->get(route('mi-dia'))->assertRedirect(route('login'));
    }
}
