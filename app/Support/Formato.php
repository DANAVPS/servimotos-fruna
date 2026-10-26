<?php

namespace App\Support;

class Formato
{
    /**
     * Pesos colombianos sin decimales: 11000 => "$11.000".
     */
    public static function pesos(float|int|string|null $valor): string
    {
        return '$' . number_format((float) $valor, 0, ',', '.');
    }
}
