<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialFalla extends Model
{
    protected $table = 'historial_fallas';

    const UPDATED_AT = null;

    protected $fillable = [
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
