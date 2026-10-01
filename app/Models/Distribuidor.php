<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATaller;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Distribuidor extends Model
{
    use HasFactory, PerteneceATaller, SoftDeletes;

    protected $table = 'distribuidores';

    protected $fillable = [
        'taller_id',
        'nombre',
        'contacto',
        'telefono_whatsapp',
        'email',
        'notas',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function repuestos(): BelongsToMany
    {
        return $this->belongsToMany(Repuesto::class, 'repuesto_distribuidor')
            ->withPivot(['taller_id', 'codigo_proveedor', 'precio_costo_proveedor', 'tiempo_entrega_dias'])
            ->withTimestamps();
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Enlace wa.me para abrir el chat con el distribuidor. Los celulares
     * colombianos de 10 dígitos reciben el indicativo 57.
     */
    public function enlaceWhatsApp(?string $mensaje = null): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $this->telefono_whatsapp);

        if ($digitos === '') {
            return null;
        }

        if (strlen($digitos) === 10 && str_starts_with($digitos, '3')) {
            $digitos = '57' . $digitos;
        }

        $url = "https://wa.me/{$digitos}";

        return $mensaje === null ? $url : $url . '?text=' . rawurlencode($mensaje);
    }
}
