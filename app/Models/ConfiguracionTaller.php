<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATaller;
use App\Support\TenantManager;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionTaller extends Model
{
    use PerteneceATaller;

    protected $table = 'configuracion_taller';

    protected $fillable = [
        'taller_id',
        'capacidad_maxima_simultanea',
        'horario_apertura',
        'horario_cierre',
        'direccion',
        'telefono_contacto',
        'email_contacto',
        'logo_path',
        'precios_incluyen_iva',
        'discriminar_iva_en_factura',
        'tasa_iva',
        'dias_alerta_amarilla',
        'dias_alerta_roja',
        // ultimo_numero_factura NO es asignable: solo lo incrementa el
        // servicio de facturación, con lockForUpdate.
    ];

    // Igual a los defaults de la base: firstOrCreate() en actual() devuelve
    // la instancia sin recargarla y estos campos llegarían vacíos.
    protected $attributes = [
        'precios_incluyen_iva' => true,
        'discriminar_iva_en_factura' => true,
        'tasa_iva' => '19.00',
        'dias_alerta_amarilla' => 2,
        'dias_alerta_roja' => 5,
        'ultimo_numero_factura' => 0,
    ];

    protected function casts(): array
    {
        return [
            'capacidad_maxima_simultanea' => 'integer',
            'precios_incluyen_iva' => 'boolean',
            'discriminar_iva_en_factura' => 'boolean',
            'tasa_iva' => 'decimal:2',
            'dias_alerta_amarilla' => 'integer',
            'dias_alerta_roja' => 'integer',
            'ultimo_numero_factura' => 'integer',
        ];
    }

    public static function actual(): self
    {
        $tallerId = app(TenantManager::class)->tallerId();

        return static::firstOrCreate(
            ['taller_id' => $tallerId],
            [
                'capacidad_maxima_simultanea' => 5,
                'horario_apertura' => '08:00:00',
                'horario_cierre' => '18:00:00',
            ]
        );
    }
}
