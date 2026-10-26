<?php

namespace App\Support;

/**
 * Normalización de identificadores colombianos, para que lo que se guarda y
 * lo que se busca coincidan sin importar cómo lo escriba el usuario.
 */
class Identificadores
{
    /** Placa de moto: ABC12D (actual) o ABC12 (antigua, sin letra final). */
    public const PATRON_PLACA_MOTO = '/^[A-Z]{3}[0-9]{2}[A-Z]?$/';

    /** Cédula, cédula de extranjería/pasaporte, o NIT con dígito de verificación. */
    public const PATRON_CEDULA_NIT = '/^[A-Z0-9]{4,15}(-[0-9])?$/';

    /** "abc-12d", "ABC 12D" => "ABC12D" */
    public static function placa(?string $placa): ?string
    {
        $limpia = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $placa));

        return $limpia === '' ? null : $limpia;
    }

    /** "1.020.304.050" => "1020304050"; "900.123.456 - 1" => "900123456-1" */
    public static function cedulaNit(?string $documento): ?string
    {
        $limpio = strtoupper(preg_replace('/[\s.,]/', '', (string) $documento));
        $limpio = preg_replace('/-+/', '-', trim($limpio, '-'));

        return $limpio === '' ? null : $limpio;
    }

    /** "300 123 4567", "57 3001234567", "+57 300-123-4567" => "+573001234567" */
    public static function telefono(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $telefono);

        if ($digitos === '') {
            return null;
        }

        if (strlen($digitos) === 10) {
            return '+57' . $digitos;
        }

        if (strlen($digitos) === 12 && str_starts_with($digitos, '57')) {
            return '+' . $digitos;
        }

        // Formato no reconocido: se devuelve tal cual para que la validación lo rechace.
        return trim((string) $telefono);
    }
}
