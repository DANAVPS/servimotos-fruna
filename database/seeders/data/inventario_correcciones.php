<?php

/**
 * Correcciones al Excel histórico acordadas con el taller (29/09/2026).
 * Se aplican al importar database/seeders/data/inventario.csv.
 * Ver InventarioCsvParser::aplicarCorrecciones().
 */
return [
    // SKU => precio de venta correcto.
    'precios' => [
        'INS-LUB-004' => 42000, // Castrol POWER 1 5W-40: venía como $420.000.
    ],

    // [SKU que queda, SKU que se absorbe...]: se suman los stocks.
    'fusionar' => [
        // Kit de Arrastre Kontrol, Honda CBF 150: fila repetida en el Excel.
        ['REP-ARR-024-2', 'REP-ARR-025-2'],
    ],
];
