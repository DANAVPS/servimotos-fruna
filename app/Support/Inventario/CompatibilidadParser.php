<?php

namespace App\Support\Inventario;

use App\Support\TextoBusqueda;

/**
 * Convierte el texto libre de compatibilidad ("Pulsar 125 / 135 / Duke 200")
 * en una lista de referencias de moto, una por fila en repuesto_compatibilidades.
 */
class CompatibilidadParser
{
    private const LONGITUD_MAXIMA = 100;

    /**
     * @param string|array<int, string>|null $entrada
     * @return array<int, string>
     */
    public static function parsear(string|array|null $entrada): array
    {
        if ($entrada === null) {
            return [];
        }

        $fragmentos = is_array($entrada)
            ? $entrada
            : preg_split('/[\/;,\r\n]+/u', $entrada);

        $resultado = [];
        $anterior = null;

        foreach ($fragmentos as $fragmento) {
            $referencia = trim(preg_replace('/\s+/u', ' ', (string) $fragmento));

            if ($referencia === '') {
                continue;
            }

            // "Pulsar 125 / 135": el "135" suelto hereda el modelo anterior.
            if ($anterior !== null && preg_match('/^\d+$/', $referencia)) {
                $prefijo = trim(preg_replace('/\s*\d+$/', '', $anterior));

                if ($prefijo !== '' && $prefijo !== $anterior) {
                    $referencia = "{$prefijo} {$referencia}";
                }
            }

            $referencia = mb_substr($referencia, 0, self::LONGITUD_MAXIMA);
            $clave = TextoBusqueda::normalizar($referencia);

            if ($clave !== '' && ! isset($resultado[$clave])) {
                $resultado[$clave] = $referencia;
            }

            $anterior = $referencia;
        }

        return array_values($resultado);
    }
}
