<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\PerteneceATaller;


class Cita extends Model
{
    use HasFactory, PerteneceATaller;

    protected $fillable = [
        'fecha',
        'hora',
        'tipo_servicio',
        'estado',
        'cliente_id',
        'moto_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
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

    public function scopePendiente($query)
    {
        return $query->where('estado', 'pendiente');
    }
}
