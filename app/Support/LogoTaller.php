<?php

namespace App\Support;

use App\Models\ConfiguracionTaller;
use Illuminate\Support\Facades\Storage;

class LogoTaller
{
    /**
     * DomPDF no carga URLs remotas por defecto: el logo va embebido.
     */
    public static function base64(?ConfiguracionTaller $configuracion): ?string
    {
        $ruta = $configuracion?->logo_path;

        if (! $ruta || ! Storage::disk('public')->exists($ruta)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($ruta) ?: 'image/png';

        return "data:{$mime};base64," . base64_encode(Storage::disk('public')->get($ruta));
    }
}
