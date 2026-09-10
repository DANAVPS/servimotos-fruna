<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mecanico extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'especialidad',
        'disponibilidad',
    ];

    protected function casts(): array
    {
        return [
            'disponibilidad' => 'boolean',
        ];
    }

    public function ordenesServicio(): HasMany
    {
        return $this->hasMany(OrdenServicio::class);
    }

    public function scopeDisponible($query)
    {
        return $query->where('disponibilidad', true);
    }
}
