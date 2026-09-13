<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATaller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialFalla extends Model
{
    use PerteneceATaller;

    protected $table = 'historial_fallas';

    const UPDATED_AT = null;

    protected $fillable = [
        'taller_id',
        'cliente_id',
        'moto_id',
        'sintomas',
        'diagnostico',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function moto(): BelongsTo
    {
        return $this->belongsTo(Moto::class);
    }
}
