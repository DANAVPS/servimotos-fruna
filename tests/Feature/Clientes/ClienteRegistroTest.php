<?php

namespace Tests\Feature\Clientes;

use App\Models\Cliente;
use App\Models\Moto;
use App\Models\Taller;
use App\Models\TipoCliente;
use App\Models\User;
use App\Services\ClienteService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

class ClienteRegistroTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taller = Taller::factory()->create();
        $this->admin = User::factory()->administradora()->conPin()->deTaller($this->taller)->create();
    }

    private function datos(array $cliente = [], array $moto = []): array
    {
        return [
            'nombre' => 'Andrés Gómez',
            'telefono' => '300 123 4567',
            'cedula_nit' => '1.020.304.050',
            'moto' => ['placa' => 'abc-12d', 'marca' => 'Yamaha', 'modelo' => 'FZ 2.0', 'anio' => 2021, ...$moto],
            ...$cliente,
        ];
    }

    public function test_registra_cliente_y_moto_en_un_solo_formulario(): void
    {
        $this->actingAs($this->admin)->post(route('clientes.store'), $this->datos())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->enTaller($this->taller);
        $cliente = Cliente::with('motos', 'tipoCliente')->sole();

        $this->assertSame('+573001234567', $cliente->telefono);
        $this->assertSame('1020304050', $cliente->cedula_nit);
        $this->assertSame('regular', $cliente->tipoCliente->slug, 'Sin tipo elegido, queda Regular.');
        $this->assertSame('ABC12D', $cliente->motos->sole()->placa);
        $this->assertSame($this->taller->id, $cliente->motos->sole()->taller_id);
    }

    public function test_acepta_placa_antigua_y_un_tipo_de_cliente_elegido(): void
    {
        $this->enTaller($this->taller);
        $vip = TipoCliente::where('slug', 'vip-flota')->first();

        $this->actingAs($this->admin)
            ->post(route('clientes.store'), $this->datos(['tipo_cliente_id' => $vip->id], ['placa' => 'xyz98']))
            ->assertSessionHasNoErrors();

        $this->assertSame($vip->id, Cliente::sole()->tipo_cliente_id);
        $this->assertSame('XYZ98', Moto::sole()->placa);
    }

    public function test_placa_duplicada_en_el_taller_da_un_error_claro(): void
    {
        $this->actingAs($this->admin)->post(route('clientes.store'), $this->datos());

        $this->actingAs($this->admin)
            ->post(route('clientes.store'), $this->datos(
                ['nombre' => 'Otra persona', 'telefono' => '3109998877', 'cedula_nit' => null],
                ['placa' => 'ABC 12D']
            ))
            ->assertSessionHasErrors(['moto.placa' => 'Esta moto ya está registrada a nombre de Andrés Gómez.']);

        $this->enTaller($this->taller);
        $this->assertSame(1, Cliente::count(), 'No debe quedar un cliente sin moto.');
    }

    public function test_la_misma_placa_si_se_puede_registrar_en_otro_taller(): void
    {
        $this->actingAs($this->admin)->post(route('clientes.store'), $this->datos());

        $adminOtroTaller = User::factory()->administradora()->conPin()->create();

        $this->actingAs($adminOtroTaller)->post(route('clientes.store'), $this->datos())
            ->assertSessionHasNoErrors();
    }

    public function test_valida_formato_de_placa_cedula_y_telefono(): void
    {
        $this->actingAs($this->admin)
            ->post(route('clientes.store'), $this->datos(
                ['telefono' => '12345', 'cedula_nit' => '12'],
                ['placa' => 'AB123C']
            ))
            ->assertSessionHasErrors(['telefono', 'cedula_nit', 'moto.placa']);

        $this->actingAs($this->admin)
            ->post(route('clientes.store'), $this->datos([], ['placa' => '', 'marca' => '']))
            ->assertSessionHasErrors(['moto.placa' => 'La placa de la moto es obligatoria.', 'moto.marca']);
    }

    public function test_cedula_nit_es_opcional_pero_unica_por_taller(): void
    {
        $this->actingAs($this->admin)->post(route('clientes.store'), $this->datos(['cedula_nit' => '']))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->post(route('clientes.store'), $this->datos(['cedula_nit' => '', 'telefono' => '3110000000'], ['placa' => 'DEF34G']))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('clientes.store'), $this->datos(['telefono' => '3120000000'], ['placa' => 'GHI56J']))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)
            ->post(route('clientes.store'), $this->datos(['telefono' => '3130000000', 'cedula_nit' => '1020304050'], ['placa' => 'JKL78M']))
            ->assertSessionHasErrors(['cedula_nit' => 'Ya hay un cliente con esta cédula o NIT.']);
    }

    public function test_si_la_moto_falla_no_queda_el_cliente_creado(): void
    {
        $this->enTaller($this->taller);
        $existente = Cliente::factory()->create(['taller_id' => $this->taller->id]);
        Moto::factory()->create(['cliente_id' => $existente->id, 'placa' => 'ABC12D']);

        try {
            app(ClienteService::class)->registrarConMoto(
                ['nombre' => 'Huérfano', 'telefono' => '3001112222'],
                ['placa' => 'ABC12D', 'marca' => 'AKT', 'modelo' => 'NKD']
            );
            $this->fail('Se esperaba violación de unicidad de placa.');
        } catch (QueryException) {
            // esperado: el índice único (taller_id, placa) es la última barrera
        }

        $this->assertFalse(Cliente::where('nombre', 'Huérfano')->exists());
    }

    public function test_editar_conserva_su_propio_telefono_y_cambia_el_estado_manualmente(): void
    {
        $this->enTaller($this->taller);
        $cliente = Cliente::factory()->create([
            'taller_id' => $this->taller->id,
            'telefono' => '3001234567',
            'fecha_ultima_visita' => now()->subMonths(11),
        ]);
        $this->enTaller(null);

        $datos = ['nombre' => $cliente->nombre, 'telefono' => '300 123 4567', 'pin_autorizacion' => '482915'];

        $this->actingAs($this->admin)
            ->put(route('clientes.update', $cliente), [...$datos, 'estado_cliente' => 'archivado'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('clientes.index'))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'activo → archivado'));

        $this->enTaller($this->taller);
        $this->assertNull(Cliente::find($cliente->id), 'Archivado: el scope lo oculta del listado por defecto.');
        $this->enTaller(null);

        // Archivado sigue siendo accesible por URL para revertirlo.
        $this->actingAs($this->admin)->get(route('clientes.edit', $cliente))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('clientes.update', $cliente), [...$datos, 'estado_cliente' => 'activo'])
            ->assertSessionHasNoErrors();

        $cliente = Cliente::withoutGlobalScopes()->find($cliente->id);
        $this->assertSame('activo', $cliente->estado_cliente);
        $this->assertTrue($cliente->fecha_ultima_visita->isToday(), 'Reactivar renueva la última visita.');

        $this->actingAs($this->admin)
            ->put(route('clientes.update', $cliente), [...$datos, 'estado_cliente' => 'borrado'])
            ->assertSessionHasErrors('estado_cliente');
    }
}
