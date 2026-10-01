<?php

namespace Tests\Feature\Clientes;

use App\Models\AuditoriaAutorizacion;
use App\Models\Cliente;
use App\Models\Moto;
use App\Models\Taller;
use App\Models\User;
use App\Services\AutorizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\UsaTaller;
use Tests\TestCase;

class PinAutorizacionTest extends TestCase
{
    use RefreshDatabase, UsaTaller;

    private const PIN = '482915';

    private Taller $taller;

    private User $admin;

    private Cliente $cliente;

    private Moto $moto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taller = Taller::factory()->create();
        $this->admin = User::factory()->administradora()->conPin(self::PIN)->deTaller($this->taller)->create();

        $this->enTaller($this->taller);
        $this->cliente = Cliente::factory()->create([
            'taller_id' => $this->taller->id,
            'nombre' => 'Andrés Gómez',
            'telefono' => '3001234567',
        ]);
        $this->moto = Moto::factory()->create(['cliente_id' => $this->cliente->id, 'placa' => 'ABC12D']);
        $this->enTaller(null);
    }

    private function datos(array $extra = []): array
    {
        return [
            'nombre' => 'Andrés Gómez Ruiz',
            'telefono' => '300 123 4567',
            'motos' => [$this->moto->id => ['placa' => 'ABC12D', 'marca' => 'Yamaha', 'modelo' => 'FZ 2.0', 'anio' => 2022]],
            'pin_autorizacion' => self::PIN,
            ...$extra,
        ];
    }

    private function intentos(): int
    {
        return app(AutorizacionService::class)->intentosFallidos($this->admin);
    }

    public function test_sin_pin_configurado_redirige_a_configurarlo(): void
    {
        $sinPin = User::factory()->administradora()->deTaller($this->taller)->create();

        $this->actingAs($sinPin)->get(route('clientes.edit', $this->cliente))
            ->assertRedirect(route('profile.edit') . '#pin-autorizacion')
            ->assertSessionHas('status-pin');

        $this->actingAs($sinPin)->putJson(route('clientes.update', $this->cliente), $this->datos())
            ->assertStatus(409)
            ->assertJsonPath('redirect', route('profile.edit') . '#pin-autorizacion');

        $this->actingAs($sinPin)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('PIN de autorización')
            ->assertSee('Configurar PIN');
    }

    public function test_pin_correcto_edita_redirige_al_listado_y_audita(): void
    {
        $this->actingAs($this->admin)
            ->put(route('clientes.update', $this->cliente), $this->datos())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('clientes.index'));

        $this->assertSame('Andrés Gómez Ruiz', $this->cliente->fresh()->nombre);

        $auditoria = AuditoriaAutorizacion::withoutGlobalScopes()->sole();
        $this->assertSame($this->admin->id, $auditoria->user_id);
        $this->assertSame($this->taller->id, $auditoria->taller_id);
        $this->assertSame('cliente.actualizar', $auditoria->accion);
        $this->assertSame(Cliente::class, $auditoria->modelo_afectado);
        $this->assertSame($this->cliente->id, $auditoria->modelo_id);
        $this->assertNotNull($auditoria->created_at);
    }

    public function test_con_el_modal_el_exito_devuelve_la_url_y_deja_el_mensaje_para_el_listado(): void
    {
        $this->actingAs($this->admin)
            ->putJson(route('clientes.update', $this->cliente), $this->datos())
            ->assertOk()
            ->assertJsonPath('redirect', route('clientes.index'));

        $this->actingAs($this->admin)->get(route('clientes.index'))
            ->assertSee('Cliente Andrés Gómez Ruiz actualizado.');
    }

    public function test_pin_incorrecto_da_403_cuenta_el_intento_y_no_guarda(): void
    {
        $this->actingAs($this->admin)
            ->putJson(route('clientes.update', $this->cliente), $this->datos(['pin_autorizacion' => '000001']))
            ->assertForbidden()
            ->assertJsonPath('message', 'PIN incorrecto. Te quedan 4 intentos.')
            ->assertJsonPath('intentos_restantes', 4);

        $this->assertSame(1, $this->intentos());
        $this->assertSame('Andrés Gómez', $this->cliente->fresh()->nombre);
        $this->assertSame(0, AuditoriaAutorizacion::withoutGlobalScopes()->count());
    }

    public function test_sin_javascript_el_pin_incorrecto_vuelve_al_formulario_sin_perder_datos_ni_guardar_el_pin(): void
    {
        $this->actingAs($this->admin)
            ->from(route('clientes.edit', $this->cliente))
            ->put(route('clientes.update', $this->cliente), $this->datos(['pin_autorizacion' => '000001']))
            ->assertRedirect(route('clientes.edit', $this->cliente))
            ->assertSessionHasErrors('pin_autorizacion')
            ->assertSessionHasInput('nombre', 'Andrés Gómez Ruiz')
            ->assertSessionMissing('_old_input.pin_autorizacion');
    }

    public function test_al_sexto_intento_queda_bloqueado_aunque_el_pin_sea_correcto(): void
    {
        foreach (range(1, 5) as $intento) {
            $this->actingAs($this->admin)
                ->putJson(route('clientes.update', $this->cliente), $this->datos(['pin_autorizacion' => '000001']))
                ->assertForbidden();
        }

        $this->assertSame(5, $this->intentos());

        $this->actingAs($this->admin)
            ->putJson(route('clientes.update', $this->cliente), $this->datos())
            ->assertForbidden()
            ->assertJsonPath('intentos_restantes', 0)
            ->assertJson(fn ($json) => $json->where('message', fn ($m) => str_starts_with($m, 'Demasiados intentos fallidos de PIN'))->etc());

        $this->assertSame('Andrés Gómez', $this->cliente->fresh()->nombre);

        $this->travel(16)->minutes();

        $this->actingAs($this->admin)
            ->putJson(route('clientes.update', $this->cliente), $this->datos())
            ->assertOk();
        $this->assertSame(0, $this->intentos(), 'Un PIN correcto limpia el contador.');
    }

    public function test_reinscribir_exige_pin(): void
    {
        Cliente::withoutGlobalScopes()->whereKey($this->cliente->id)->update(['estado_cliente' => 'archivado']);

        $this->actingAs($this->admin)->postJson(route('clientes.reinscribir', $this->cliente))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pin_autorizacion');

        $this->actingAs($this->admin)->postJson(route('clientes.reinscribir', $this->cliente), ['pin_autorizacion' => '999999'])
            ->assertForbidden();

        $this->actingAs($this->admin)->post(route('clientes.reinscribir', $this->cliente), ['pin_autorizacion' => self::PIN])
            ->assertRedirect(route('clientes.index'));

        $this->assertSame('activo', Cliente::withoutGlobalScopes()->find($this->cliente->id)->estado_cliente);
        $this->assertSame('cliente.reinscribir', AuditoriaAutorizacion::withoutGlobalScopes()->sole()->accion);
    }

    public function test_editar_moto_y_agregar_una_nueva(): void
    {
        $this->actingAs($this->admin)
            ->put(route('clientes.update', $this->cliente), $this->datos([
                'motos' => [$this->moto->id => ['placa' => 'abc-12e', 'marca' => 'Honda', 'modelo' => 'CB 125F', 'anio' => 2020]],
                'nueva_moto' => ['placa' => 'xyz98', 'marca' => 'AKT', 'modelo' => 'NKD 125'],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['ABC12E', 'XYZ98'], $this->cliente->motos()->orderBy('placa')->pluck('placa')->all());
        $this->assertSame('Honda', $this->moto->fresh()->marca);
    }

    public function test_la_moto_conserva_su_propia_placa_pero_no_puede_tomar_una_ajena(): void
    {
        $this->enTaller($this->taller);
        $otro = Cliente::factory()->create(['taller_id' => $this->taller->id, 'nombre' => 'Carlos Pérez']);
        Moto::factory()->create(['cliente_id' => $otro->id, 'placa' => 'DEF34G']);
        $this->enTaller(null);

        $this->actingAs($this->admin)
            ->put(route('clientes.update', $this->cliente), $this->datos())
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->put(route('clientes.update', $this->cliente), $this->datos([
                'motos' => [$this->moto->id => ['placa' => 'DEF 34G', 'marca' => 'Yamaha', 'modelo' => 'FZ']],
            ]))
            ->assertSessionHasErrors(["motos.{$this->moto->id}.placa" => 'Esta moto ya está registrada a nombre de Carlos Pérez.']);

        $this->actingAs($this->admin)
            ->put(route('clientes.update', $this->cliente), $this->datos([
                'nueva_moto' => ['placa' => 'ABC12D', 'marca' => 'AKT', 'modelo' => 'NKD'],
            ]))
            ->assertSessionHasErrors('motos');

        $this->assertSame('ABC12D', $this->moto->fresh()->placa);
    }

    public function test_no_se_puede_editar_la_moto_de_otro_cliente_por_el_formulario(): void
    {
        $this->enTaller($this->taller);
        $ajena = Moto::factory()->create(['cliente_id' => Cliente::factory()->create(['taller_id' => $this->taller->id])->id, 'placa' => 'GHI56J']);
        $this->enTaller(null);

        $this->actingAs($this->admin)
            ->put(route('clientes.update', $this->cliente), $this->datos([
                'motos' => [$ajena->id => ['placa' => 'GHI56J', 'marca' => 'Robada', 'modelo' => 'X']],
            ]))
            ->assertSessionHasErrors('motos');

        $this->assertNotSame('Robada', $ajena->fresh()->marca);
    }

    public function test_configurar_pin_exige_6_digitos_y_rechaza_pines_debiles(): void
    {
        $sinPin = User::factory()->administradora()->deTaller($this->taller)->create();

        foreach (['1234', '1234567', 'abcdef', '123456', '000000'] as $invalido) {
            $this->actingAs($sinPin)
                ->put(route('profile.pin.update'), ['pin' => $invalido, 'pin_confirmation' => $invalido])
                ->assertSessionHasErrorsIn('pinAutorizacion', 'pin');
        }

        $this->actingAs($sinPin)
            ->put(route('profile.pin.update'), ['pin' => '482915', 'pin_confirmation' => '482916'])
            ->assertSessionHasErrorsIn('pinAutorizacion', 'pin');

        $this->assertFalse($sinPin->fresh()->tienePinConfigurado());

        $this->actingAs($sinPin)
            ->put(route('profile.pin.update'), ['pin' => '482915', 'pin_confirmation' => '482915'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'pin-configurado');

        $guardado = $sinPin->fresh()->pin_autorizacion;
        $this->assertNotSame('482915', $guardado, 'El PIN se guarda hasheado.');
        $this->assertTrue(Hash::check('482915', $guardado));
    }

    public function test_cambiar_pin_exige_contrasena_y_pin_anterior(): void
    {
        $this->actingAs($this->admin)
            ->put(route('profile.pin.update'), ['pin' => '730264', 'pin_confirmation' => '730264'])
            ->assertSessionHasErrorsIn('pinAutorizacion', ['current_password', 'pin_actual']);

        $this->actingAs($this->admin)
            ->put(route('profile.pin.update'), [
                'current_password' => 'password',
                'pin_actual' => '111222',
                'pin' => '730264',
                'pin_confirmation' => '730264',
            ])
            ->assertSessionHasErrorsIn('pinAutorizacion', 'pin_actual');

        $this->assertSame(1, $this->intentos(), 'El PIN anterior equivocado también cuenta como intento.');

        $this->actingAs($this->admin)
            ->put(route('profile.pin.update'), [
                'current_password' => 'password',
                'pin_actual' => self::PIN,
                'pin' => '730264',
                'pin_confirmation' => '730264',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'pin-actualizado');

        $this->assertTrue($this->admin->fresh()->verificarPin('730264'));
    }

    public function test_el_mecanico_no_configura_pin_ni_ve_la_seccion(): void
    {
        $mecanico = User::factory()->mecanico()->deTaller($this->taller)->create();

        $this->actingAs($mecanico)
            ->put(route('profile.pin.update'), ['pin' => '482915', 'pin_confirmation' => '482915'])
            ->assertForbidden();

        $this->actingAs($mecanico)->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('PIN de autorización');
    }

    public function test_el_pin_no_se_expone_al_serializar_el_usuario(): void
    {
        $this->assertArrayNotHasKey('pin_autorizacion', $this->admin->fresh()->toArray());
    }
}
