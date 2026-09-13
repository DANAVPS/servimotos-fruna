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
    ];

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
