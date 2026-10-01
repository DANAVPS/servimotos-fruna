<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATaller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaAutorizacion extends Model
{
    use PerteneceATaller;

    protected $table = 'auditoria_autorizaciones';

    public const UPDATED_AT = null;

    protected $fillable = [
        'taller_id',
        'user_id',
        'accion',
        'modelo_afectado',
        'modelo_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
