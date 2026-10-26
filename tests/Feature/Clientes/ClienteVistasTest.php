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

class ClienteVistasTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private Taller $taller;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taller = Taller::factory()->create();
        $this->enTaller($this->taller);
        $vip = TipoCliente::where('slug', 'vip-flota')->first();
        $this->cliente = Cliente::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Mensajería Rápida SAS',
            'cedula_nit' => '900123456-1',
            'tipo_cliente_id' => $vip->id,
        ]);
        Moto::factory()->create(['cliente_id' => $this->cliente->id, 'placa' => 'ABC12D', 'marca' => 'AKT', 'modelo' => 'NKD 125']);
        $this->enTaller(null);
    }

    public function test_la_administradora_ve_listado_con_tipo_y_acciones(): void
    {
        $admin = User::factory()->administradora()->conPin()->deTaller($this->taller)->create();

        $this->actingAs($admin)->get(route('clientes.index', ['buscar' => 'abc12d']))
            ->assertOk()
            ->assertSee('Mensajería Rápida SAS')
            ->assertSee('900123456-1')
            ->assertSee('VIP / Flota · −10%')
            ->assertSee(route('clientes.edit', $this->cliente))
            ->assertSee('+ Nuevo cliente')
            ->assertSee('Tipos de Cliente');
    }

    public function test_el_mecanico_no_ve_botones_de_gestion(): void
    {
        $mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();

        $this->actingAs($mecanico)->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('Mensajería') // solo el primer nombre
            ->assertDontSee(route('clientes.edit', $this->cliente))
            ->assertDontSee('+ Nuevo cliente')
            ->assertDontSee(route('tipos-cliente.index'));

        $this->actingAs($mecanico)->get(route('clientes.show', $this->cliente))
            ->assertOk()
            ->assertSee('ABC12D')
            ->assertDontSee(route('clientes.edit', $this->cliente));
    }

    public function test_formulario_de_crear_incluye_la_moto_y_regular_por_defecto(): void
    {
        $admin = User::factory()->administradora()->conPin()->deTaller($this->taller)->create();

        $this->actingAs($admin)->get(route('clientes.create'))
            ->assertOk()
            ->assertSee('name="moto[placa]"', false)
            ->assertSee('name="moto[marca]"', false)
            ->assertSee('Regular')
            ->assertSee('VIP / Flota');
    }

    public function test_edicion_muestra_estado_moto_editable_historial_y_pin(): void
    {
        $admin = User::factory()->administradora()->conPin()->deTaller($this->taller)->create();

        $this->actingAs($admin)->get(route('clientes.edit', $this->cliente))
            ->assertOk()
            ->assertSee('name="estado_cliente"', false)
            ->assertSee('name="motos[' . $this->cliente->motos()->value('id') . '][placa]"', false)
            ->assertSee('value="ABC12D"', false)
            ->assertSee('name="nueva_moto[placa]"', false)
            ->assertSee(route('clientes.show', $this->cliente))
            ->assertSee('Ver historial de órdenes')
            ->assertSee('formularioConPin()');
    }

    public function test_reinscribir_desde_el_listado_reactiva_al_archivado(): void
    {
        $admin = User::factory()->administradora()->conPin()->deTaller($this->taller)->create();
        Cliente::withoutGlobalScopes()->whereKey($this->cliente->id)->update(['estado_cliente' => 'archivado']);

        $this->actingAs($admin)->get(route('clientes.index', ['estado' => 'archivado']))
            ->assertSee('Reinscribir');

        $this->actingAs($admin)->post(route('clientes.reinscribir', $this->cliente), ['pin_autorizacion' => '482915'])
            ->assertRedirect(route('clientes.index'));

        $this->assertSame('activo', Cliente::withoutGlobalScopes()->find($this->cliente->id)->estado_cliente);
    }

    public function test_vistas_de_tipos_de_cliente(): void
    {
        $admin = User::factory()->administradora()->conPin()->deTaller($this->taller)->create();
        $this->enTaller($this->taller);
        $regular = TipoCliente::where('slug', 'regular')->first();
        $vip = TipoCliente::where('slug', 'vip-flota')->first();
        $this->enTaller(null);

        $this->actingAs($admin)->get(route('tipos-cliente.index'))
            ->assertOk()
            ->assertSeeInOrder(['Regular', '(por defecto)', 'VIP / Flota', '10%']);
        $this->actingAs($admin)->get(route('tipos-cliente.create'))->assertOk()->assertSee('Descuento en repuestos');
        $this->actingAs($admin)->get(route('tipos-cliente.edit', $vip))
            ->assertOk()
            ->assertSee('1 cliente tiene este tipo.');
        $this->actingAs($admin)->get(route('tipos-cliente.edit', $regular))
            ->assertOk()
            ->assertSee('Regular no se puede desactivar');
    }
}
