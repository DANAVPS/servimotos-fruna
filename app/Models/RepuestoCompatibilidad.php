<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATaller;
use App\Support\TextoBusqueda;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepuestoCompatibilidad extends Model
{
    use PerteneceATaller;

    protected $table = 'repuesto_compatibilidades';

    protected $fillable = [
        'taller_id',
        'repuesto_id',
        'moto_referencia',
    ];

    /**
     * Al asignar la referencia se calcula su versión normalizada, que es
     * la que garantiza que "FZ16" y "FZ 16" no queden duplicadas.
     */
    protected function motoReferencia(): Attribute
    {
        return Attribute::make(
            set: fn (string $valor) => [
                'moto_referencia' => trim(preg_replace('/\s+/u', ' ', $valor)),
                'moto_referencia_normalizada' => TextoBusqueda::normalizar($valor),
            ],
        );
    }

    public function repuesto(): BelongsTo
    {
        return $this->belongsTo(Repuesto::class);
    }
}
