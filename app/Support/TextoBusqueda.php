<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalización de texto para el buscador del inventario.
 *
 * Se eliminan tildes, mayúsculas, espacios y signos, de modo que
 * "FZ 16", "fz16" y "Fz-16" produzcan la misma clave, y "bujia"
 * encuentre "Bujías". El índice se guarda en repuestos.busqueda.
 */
class TextoBusqueda
{
    public static function normalizar(?string $texto): string
    {
        if ($texto === null || $texto === '') {
            return '';
        }

        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($texto)));
    }

    /**
     * Divide lo que escribió el usuario en términos normalizados.
     * Cada término debe aparecer en el índice (AND entre términos).
     *
     * @return array<int, string>
     */
    public static function terminos(?string $consulta): array
    {
        $palabras = preg_split('/\s+/u', trim((string) $consulta)) ?: [];

        return array_values(array_unique(array_filter(
            array_map([self::class, 'normalizar'], $palabras),
            fn (string $termino) => $termino !== ''
        )));
    }

    /**
     * Construye el índice de búsqueda. Cada parte se normaliza por
     * separado y se unen con espacio, para que un término no pueda
     * "saltar" de un campo a otro.
     *
     * @param array<int, string|null> $partes
     */
    public static function indice(array $partes): string
    {
        return implode(' ', array_filter(
            array_map([self::class, 'normalizar'], $partes),
            fn (string $parte) => $parte !== ''
        ));
    }
}
