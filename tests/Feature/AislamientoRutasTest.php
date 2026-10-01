<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Repuesto;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

/**
 * El contexto de tenant debe fijarse ANTES de resolver los {parámetros} de la
 * ruta. Si no, el route model binding carga registros de otro taller y solo
 * quedan protegidos los controladores que tengan Policy.
 */
class AislamientoRutasTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    public function test_la_administradora_de_otro_taller_recibe_404_con_ids_ajenos(): void
    {
        $tallerA = Taller::factory()->create();
        $this->enTaller($tallerA);
        $clienteA = Cliente::create(['nombre' => 'Cliente de A', 'telefono' => '3001112233', 'estado_cliente' => 'activo']);
        $repuestoA = Repuesto::factory()->create(['taller_id' => $tallerA->id]);
        $this->enTaller(null);

        $adminB = User::factory()->administradora()->conPin()->create();

        $this->actingAs($adminB)->get(route('clientes.show', $clienteA))->assertNotFound();
        $this->actingAs($adminB)->get(route('clientes.edit', $clienteA))->assertNotFound();
        $this->actingAs($adminB)->get(route('inventario.edit', $repuestoA))->assertNotFound();
    }
}
