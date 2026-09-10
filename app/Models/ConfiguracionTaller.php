<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionTaller extends Model
{
    protected $table = 'configuracion_taller';

    protected $fillable = [
        'capacidad_maxima_simultanea',
        'horario_apertura',
        'horario_cierre',
    ];

    /**
     * Esta tabla es de fila única (patrón singleton de configuración).
     */
    public static function actual(): self
    {
        return static::firstOrCreate([], [
            'capacidad_maxima_simultanea' => 5,
            'horario_apertura' => '08:00:00',
            'horario_cierre' => '18:00:00',
        ]);
    }
}
