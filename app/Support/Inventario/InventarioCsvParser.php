<?php

namespace App\Support\Inventario;

use App\Models\CategoriaRepuesto;
use App\Support\TextoBusqueda;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lee la plantilla de inventario exportada desde Excel (inventario.csv) y
 * la convierte en filas limpias listas para InventarioService::crear().
 *
 * Columnas: A SKU · B referencia proveedor · C nombre · D categoría ·
 * E compatibilidad · F ubicación · G stock · H stock mínimo · I costo ·
 * J precio venta · K extra (relación piñón/corona en kits) · L total (se ignora).
 *
 * Reglas de relleno (el Excel tiene celdas combinadas):
 *  - D (categoría) se arrastra hacia abajo; cuando cambia empieza una "sección".
 *  - C (nombre) se arrastra dentro de la sección y se reinicia al cambiar de sección.
 *  - Si C trae solo una marca ("KONTROL", "sUZUKI"), se conserva el artículo
 *    anterior de la sección y se le añade la marca ("Filtro de aire Suzuki").
 *  - Si la sección no trae artículo, el nombre sale de la categoría.
 *
 * Todo nombre armado por deducción queda marcado en `avisos` para revisión.
 */
class InventarioCsvParser
{
    public const FACTOR_COSTO = 0.6;

    private const COL_SKU = 0;
    private const COL_REFERENCIA = 1;
    private const COL_NOMBRE = 2;
    private const COL_CATEGORIA = 3;
    private const COL_COMPATIBILIDAD = 4;
    private const COL_UBICACION = 5;
    private const COL_STOCK = 6;
    private const COL_STOCK_MINIMO = 7;
    private const COL_COSTO = 8;
    private const COL_VENTA = 9;
    private const COL_EXTRA = 10;
    private const COLUMNAS_DATOS = 11;

    /** Marcas de producto que aparecen solas en la columna C (clave normalizada => forma canónica). */
    private const MARCAS = [
        'k&n' => 'K&N',
        'ngk' => 'NGK',
        'castrol' => 'Castrol',
        'motul' => 'Motul',
        'mobil' => 'Mobil',
        'novelbat' => 'Novelbat',
        'kontrol' => 'Kontrol',
        'japan' => 'Japan',
        'vitrix' => 'Vitrix',
        'varroc' => 'Varroc',
        'pxr' => 'PXR',
        'tvs' => 'TVS',
        'akt' => 'AKT',
        'honda' => 'Honda',
        'yamaha' => 'Yamaha',
        'bajaj' => 'Bajaj',
        'suzuki' => 'Suzuki',
        'hero' => 'Hero',
    ];

    /** Valor de la columna D (normalizado) => slug de categoría. */
    private const ALIAS_SECCION = [
        'filtros' => 'filtros',
        'filtro' => 'filtros',
        'culatin' => 'culatin',
        'culata' => 'culata',
        'clutch' => 'clutch',
        'tapa bolante' => 'tapa-bolante',
        'tapa volante' => 'tapa-bolante',
        'pistas cunas' => 'pistas-cunas',
        'pistas' => 'pistas-cunas',
        'canastilla' => 'canastilla',
        'cunas' => 'cunas',
        'bujes de tijera' => 'bujes-de-tijera',
        'fuelles de suspencion' => 'fuelles-de-suspension',
        'fuelles de suspension' => 'fuelles-de-suspension',
        'balancines' => 'balancines',
        'arbol de levas' => 'arbol-de-levas',
        'kit cilindro' => 'kit-cilindro',
        'cilindro pinton' => 'cilindro-piston',
        'cilindro piston' => 'cilindro-piston',
        'bobinas' => 'bobinas',
        'relay' => 'relay',
        'regulador' => 'regulador',
        'bujias' => 'bujias',
        'bujia' => 'bujias',
        'empaques' => 'empaques-varios',
        'tensor' => 'tensor',
        'cadenillas' => 'cadenillas',
        'cadena' => 'cadena',
        'kit de arrastre' => 'kit-de-arrastre',
        'bateria' => 'bateria',
        'baterias' => 'bateria',
        'frenos' => 'frenos',
        'lubricante' => 'lubricante',
        'lubricantes' => 'lubricante',
    ];

    /**
     * Cuando el artículo (columna C) es de estos tipos, manda sobre la columna D.
     * Ej.: C "Tensor (GUIAS)" con D "Cadenilla" es un tensor; C "Kit de Arrastre"
     * con D "Trasmision" es un kit de arrastre; C "BOBINAS" con D "ELECTRICO" es bobina.
     */
    private const ARTICULO_PRIORITARIO = [
        'conector filtro de aire' => 'conector-filtro-de-aire',
        'kit de arrastre' => 'kit-de-arrastre',
        'cadenilla' => 'cadenillas',
        'regulador' => 'regulador',
        'bateria' => 'bateria',
        'bobina' => 'bobinas',
        'tensor' => 'tensor',
        'cadena' => 'cadena',
        'relay' => 'relay',
    ];

    /** Palabras que cuentan como "el nombre menciona la categoría". */
    private const SINONIMOS_CATEGORIA = [
        'frenos' => ['pastilla', 'banda'],
        'lubricante' => ['aceite', 'lube', 'w30', 'w40', 'w50'],
        'empaques-varios' => ['empaque'],
    ];

    /**
     * Secciones cuyas filas sin artículo son empaques (en el Excel la celda
     * "Empaque" está combinada sobre ellas): "Empaque culata", "Empaque tapa bolante".
     */
    private const SECCIONES_DE_EMPAQUE = ['culata', 'tapa-bolante'];

    /** Referencias de bujía (NGK) que en el Excel vienen en la columna de compatibilidad. */
    private const PATRON_CODIGO_BUJIA = '/^[A-Z]{1,4}\d[A-Z0-9]*$/';

    /** Errores de digitación frecuentes en la columna D, para mostrarla en nombres. */
    private const CORRECCIONES = [
        'tensro' => 'tensor',
        'suspencion' => 'suspensión',
        'pinton' => 'pistón',
    ];

    public function __construct(private int $stockMinimoPorDefecto = 1)
    {
    }

    /**
     * @return array{filas: array<int, array<string, mixed>>, omitidas: array<int, array{linea: int, motivo: string}>}
     */
    public function parsearArchivo(string $ruta): array
    {
        $bytes = @file_get_contents($ruta);

        if ($bytes === false) {
            throw new RuntimeException("No se pudo leer el archivo de inventario: {$ruta}");
        }

        return $this->parsear($bytes);
    }

    /**
     * @return array{filas: array<int, array<string, mixed>>, omitidas: array<int, array{linea: int, motivo: string}>}
     */
    public function parsear(string $bytes): array
    {
        $lineas = preg_split('/\r\n|\r|\n/', $this->decodificar($bytes));
        $delimitador = substr_count($lineas[0] ?? '', ';') >= substr_count($lineas[0] ?? '', ',') ? ';' : ',';

        $filas = [];
        $omitidas = [];
        $skusVistos = [];

        $seccionClave = null;
        $seccionTexto = null;
        $marcaSeccion = null;
        $base = null;
        $baseLinea = null;
        $marca = null;

        foreach ($lineas as $indice => $linea) {
            $numeroLinea = $indice + 1;

            if ($indice === 0) {
                $this->validarEncabezado(str_getcsv($linea, $delimitador, '"', ''));
                continue;
            }

            $celdas = array_map(
                fn ($valor) => $this->limpiar((string) $valor),
                array_pad(str_getcsv($linea, $delimitador, '"', ''), self::COLUMNAS_DATOS + 1, '')
            );

            if ($this->esVacia($celdas)) {
                continue;
            }

            if ($this->tieneFormula($celdas)) {
                $omitidas[] = ['linea' => $numeroLinea, 'motivo' => 'Fila con fórmula (totales).'];
                continue;
            }

            $precioVenta = $this->numero($celdas[self::COL_VENTA]);

            if ($precioVenta === null || $precioVenta <= 0) {
                $omitidas[] = ['linea' => $numeroLinea, 'motivo' => 'Sin precio de venta.'];
                continue;
            }

            $avisos = [];

            // Sección (columna D con arrastre).
            $categoriaCruda = $celdas[self::COL_CATEGORIA];

            if ($categoriaCruda !== '') {
                $clave = $this->clave($categoriaCruda);

                if ($clave !== $seccionClave) {
                    $seccionClave = $clave;
                    $seccionTexto = $categoriaCruda;
                    $marcaSeccion = $this->marcaCanonica($categoriaCruda);
                    $base = null;
                    $baseLinea = null;
                    $marca = null;
                }
            }

            // Artículo (columna C con arrastre dentro de la sección).
            $nombreCrudo = $celdas[self::COL_NOMBRE];
            $marcaDeEstaFila = false;

            if ($nombreCrudo !== '') {
                $marcaSola = $this->marcaCanonica($nombreCrudo);

                if ($marcaSola !== null) {
                    $marca = $marcaSola;
                    $marcaDeEstaFila = true;
                } else {
                    $base = $nombreCrudo;
                    $baseLinea = $numeroLinea;
                    $marca = null;
                }
            }

            $slug = $this->slugCategoria($base, $seccionClave);

            if ($slug === null) {
                $avisos[] = 'No se pudo determinar la categoría.';
            }

            $nombre = $this->construirNombre($base, $marca ?? $marcaSeccion, $slug, $seccionTexto, $avisos);

            if ($marcaDeEstaFila && $base !== null && $baseLinea !== $numeroLinea) {
                $avisos[] = "Nombre armado con la marca de esta fila y el artículo de la línea {$baseLinea}: verificar.";
            }

            $extra = $celdas[self::COL_EXTRA];

            if ($extra !== '') {
                if (preg_match('/^\d{1,3}\s*\/\s*\d{1,3}$/', $extra)) {
                    $nombre .= ' ' . preg_replace('/\s+/', '', $extra);
                } else {
                    $avisos[] = "Columna K ignorada: \"{$extra}\".";
                }
            }

            $compatibilidades = CompatibilidadParser::parsear($celdas[self::COL_COMPATIBILIDAD]);

            $referencia = $celdas[self::COL_REFERENCIA] ?: null;

            // En Bujías, la columna E trae el código de la bujía (CR8E), no motos.
            if ($slug === 'bujias') {
                $codigos = array_values(array_filter(
                    $compatibilidades,
                    fn (string $valor) => preg_match(self::PATRON_CODIGO_BUJIA, $valor) === 1
                ));

                if ($codigos !== []) {
                    $compatibilidades = array_values(array_diff($compatibilidades, $codigos));
                    $referencia ??= implode(' / ', $codigos);
                }
            }

            $sku = $this->sku($celdas[self::COL_SKU], $skusVistos, $avisos);

            $stock = $this->numero($celdas[self::COL_STOCK]);

            if ($stock === null) {
                $avisos[] = 'Stock vacío: se toma 0.';
            }

            $stockMinimo = $this->numero($celdas[self::COL_STOCK_MINIMO]);
            $costo = $this->numero($celdas[self::COL_COSTO]);
            $costoCalculado = $costo === null || $costo <= 0;

            if ($costoCalculado) {
                $costo = round($precioVenta * self::FACTOR_COSTO, 2);
            } elseif ($costo > $precioVenta) {
                $avisos[] = 'El costo es mayor que el precio de venta.';
            }

            $filas[] = [
                'linea' => $numeroLinea,
                'codigo_sku' => $sku,
                'referencia_proveedor' => $referencia,
                'nombre' => mb_substr($nombre, 0, 255),
                'categoria_slug' => $slug,
                'compatibilidades' => $compatibilidades,
                'ubicacion_estante' => $celdas[self::COL_UBICACION] ?: null,
                'stock_actual' => max((int) ($stock ?? 0), 0),
                'stock_minimo' => $stockMinimo === null ? $this->stockMinimoPorDefecto : max((int) $stockMinimo, 0),
                'stock_minimo_por_defecto' => $stockMinimo === null,
                'precio_costo' => $costo,
                'costo_calculado' => $costoCalculado,
                'precio_venta' => $precioVenta,
                'avisos' => $avisos,
            ];
        }

        $this->marcarPreciosAtipicos($filas);

        return ['filas' => $filas, 'omitidas' => $omitidas];
    }

    /**
     * Correcciones puntuales de datos acordadas con el taller, por SKU
     * (ver database/seeders/data/inventario_correcciones.php):
     *  - `precios`: SKU => precio de venta correcto (el costo calculado se recalcula).
     *  - `fusionar`: [SKU destino, SKU a absorber...]: filas repetidas que son el
     *    mismo repuesto; se suman los stocks en la primera y se descartan las demás.
     *
     * @param array{filas: array<int, array<string, mixed>>, omitidas: array<int, array{linea: int, motivo: string}>} $resultado
     * @param array{precios?: array<string, float|int>, fusionar?: array<int, array<int, string>>} $correcciones
     * @return array{filas: array<int, array<string, mixed>>, omitidas: array<int, array{linea: int, motivo: string}>}
     */
    public function aplicarCorrecciones(array $resultado, array $correcciones): array
    {
        $filas = collect($resultado['filas'])->keyBy(fn ($fila) => $fila['codigo_sku'] ?? 'linea-' . $fila['linea']);

        foreach ($correcciones['precios'] ?? [] as $sku => $precio) {
            $fila = $filas->get($sku) ?? throw new RuntimeException("Corrección de precio: no existe el SKU {$sku}.");

            $fila['avisos'] = array_values(array_filter(
                $fila['avisos'],
                fn (string $aviso) => ! str_starts_with($aviso, 'Precio muy por encima')
            ));
            $fila['avisos'][] = 'Precio corregido de $' . number_format($fila['precio_venta'], 0, ',', '.')
                . ' a $' . number_format($precio, 0, ',', '.') . '.';
            $fila['precio_venta'] = (float) $precio;

            if ($fila['costo_calculado']) {
                $fila['precio_costo'] = round($precio * self::FACTOR_COSTO, 2);
            }

            $filas->put($sku, $fila);
        }

        foreach ($correcciones['fusionar'] ?? [] as $grupo) {
            $destino = array_shift($grupo);
            $absorbidos = $grupo;
            $fila = $filas->get($destino) ?? throw new RuntimeException("Fusión: no existe el SKU {$destino}.");

            foreach ($absorbidos as $sku) {
                $absorbida = $filas->pull($sku) ?? throw new RuntimeException("Fusión: no existe el SKU {$sku}.");
                $fila['stock_actual'] += $absorbida['stock_actual'];
                $fila['avisos'][] = "Fusionado con la línea {$absorbida['linea']} ({$sku}): mismo repuesto repetido.";
            }

            $filas->put($destino, $fila);
        }

        return ['filas' => $filas->values()->all(), 'omitidas' => $resultado['omitidas']];
    }

    /**
     * El CSV exportado por Excel para Mac usa Mac Roman y fin de línea CR;
     * el de Windows usa Windows-1252. Si ya es UTF-8 se respeta.
     */
    private function decodificar(string $bytes): string
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        }

        if (mb_check_encoding($bytes, 'UTF-8')) {
            return $bytes;
        }

        $origen = preg_match("/\r(?!\n)/", $bytes) === 1 ? 'MACINTOSH' : 'WINDOWS-1252';
        $texto = iconv($origen, 'UTF-8', $bytes);

        if ($texto === false) {
            throw new RuntimeException("No se pudo convertir el archivo desde {$origen} a UTF-8.");
        }

        return $texto;
    }

    /**
     * @param array<int, string|null> $encabezado
     */
    private function validarEncabezado(array $encabezado): void
    {
        if (count($encabezado) < self::COLUMNAS_DATOS - 1
            || ! str_contains($this->clave((string) ($encabezado[self::COL_NOMBRE] ?? '')), 'nombre')) {
            throw new RuntimeException(
                'El archivo no tiene el formato esperado: la primera fila debe ser el encabezado '
                . 'y la columna C debe ser el nombre del artículo.'
            );
        }
    }

    private function construirNombre(?string $base, ?string $marca, ?string $slug, ?string $seccionTexto, array &$avisos): string
    {
        $nombreCategoria = $slug !== null ? CategoriaRepuesto::BASE[$slug] : null;

        if ($base === null && in_array($slug, self::SECCIONES_DE_EMPAQUE, true)) {
            $base = 'Empaque';
        }

        if ($base === null) {
            $avisos[] = 'La fila no trae artículo: nombre tomado de la categoría, verificar.';
            $nombre = $nombreCategoria ?? $this->formatear($seccionTexto ?? 'Repuesto');

            return $marca !== null ? "{$nombre} {$marca}" : $nombre;
        }

        $nombre = $this->formatear($base);

        if ($marca !== null) {
            $sinMarcas = $this->quitarMarcas($nombre);
            $nombre = trim(($sinMarcas !== '' ? $sinMarcas : ($nombreCategoria ?? '')) . " {$marca}");
        }

        // "Empaque" solo no dice de qué es: se completa con la sección.
        if (preg_match('/^empaques?\b\s*(.*)$/iu', $nombre, $partes)) {
            $calificador = $slug !== null && $slug !== 'empaques-varios'
                ? mb_strtolower($nombreCategoria)
                : mb_strtolower($this->corregir($seccionTexto ?? ''));

            if ($calificador !== '' && ! str_contains(TextoBusqueda::normalizar($partes[1]), TextoBusqueda::normalizar($calificador))) {
                return trim("Empaque {$calificador} {$partes[1]}");
            }

            return $nombre;
        }

        if ($nombreCategoria !== null && ! $this->mencionaCategoria($nombre, $slug)) {
            // Nombres sin contexto ("PATA LARGA" en Bujías) se prefijan con la
            // categoría. Los que traen números (referencias, viscosidades) no.
            if (! preg_match('/\d/', $nombre)) {
                return $nombreCategoria . ' ' . $this->minusculaInicial($nombre);
            }

            $avisos[] = 'El nombre no menciona la categoría: verificar.';
        }

        return $nombre;
    }

    private function slugCategoria(?string $base, ?string $seccionClave): ?string
    {
        if ($base !== null) {
            $claveBase = $this->clave($base);

            foreach (self::ARTICULO_PRIORITARIO as $prefijo => $slug) {
                if (str_starts_with($claveBase, $prefijo)) {
                    return $slug;
                }
            }
        }

        if ($seccionClave !== null) {
            if (isset(self::ALIAS_SECCION[$seccionClave])) {
                return self::ALIAS_SECCION[$seccionClave];
            }

            // "arbol de levas akt" => "arbol de levas"
            $alias = array_keys(self::ALIAS_SECCION);
            usort($alias, fn ($a, $b) => strlen($b) <=> strlen($a));

            foreach ($alias as $clave) {
                if (str_starts_with($seccionClave, $clave . ' ')) {
                    return self::ALIAS_SECCION[$clave];
                }
            }
        }

        if ($base !== null && str_starts_with($this->clave($base), 'empaque')) {
            return 'empaques-varios';
        }

        return null;
    }

    private function mencionaCategoria(string $nombre, ?string $slug): bool
    {
        if ($slug === null) {
            return true;
        }

        $normalizado = TextoBusqueda::normalizar($nombre);
        $primeraPalabra = explode(' ', $this->clave(CategoriaRepuesto::BASE[$slug]))[0];
        $raices = [rtrim($primeraPalabra, 's'), ...(self::SINONIMOS_CATEGORIA[$slug] ?? [])];

        foreach ($raices as $raiz) {
            if (str_contains($normalizado, TextoBusqueda::normalizar($raiz))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Marca precios más de 5 veces por encima de la mediana de su categoría
     * (ej. un aceite de $420.000 cuando los demás rondan $40.000).
     *
     * @param array<int, array<string, mixed>> $filas
     */
    private function marcarPreciosAtipicos(array &$filas): void
    {
        $porCategoria = [];

        foreach ($filas as $fila) {
            $porCategoria[$fila['categoria_slug'] ?? ''][] = $fila['precio_venta'];
        }

        foreach ($filas as &$fila) {
            $precios = $porCategoria[$fila['categoria_slug'] ?? ''];

            if (count($precios) < 3) {
                continue;
            }

            sort($precios);
            $mitad = intdiv(count($precios), 2);
            $mediana = count($precios) % 2 ? $precios[$mitad] : ($precios[$mitad - 1] + $precios[$mitad]) / 2;

            if ($fila['precio_venta'] > $mediana * 5) {
                $fila['avisos'][] = 'Precio muy por encima del resto de la categoría: verificar.';
            }
        }
    }

    /**
     * SKU en mayúsculas. Si el Excel repite un SKU (pasa con REP-ARR-016..030),
     * la segunda aparición recibe sufijo "-2" para no perder la fila.
     *
     * @param array<string, true> $vistos
     */
    private function sku(string $valor, array &$vistos, array &$avisos): ?string
    {
        if ($valor === '') {
            return null;
        }

        $original = mb_strtoupper($valor);
        $sku = $original;
        $sufijo = 2;

        while (isset($vistos[$sku])) {
            $sku = "{$original}-{$sufijo}";
            $sufijo++;
        }

        if ($sku !== $original) {
            $avisos[] = "SKU {$original} repetido en el archivo: se registró como {$sku}.";
        }

        $vistos[$sku] = true;

        return $sku;
    }

    /**
     * Acepta "6600", "6.600", "6.600,00", "$ 6,600" y similares (pesos colombianos).
     */
    private function numero(string $valor): ?float
    {
        $limpio = preg_replace('/[^\d,.\-]/', '', $valor);

        if ($limpio === '' || $limpio === '-') {
            return null;
        }

        if (str_contains($limpio, ',') && str_contains($limpio, '.')) {
            $limpio = strrpos($limpio, ',') > strrpos($limpio, '.')
                ? str_replace(['.', ','], ['', '.'], $limpio)
                : str_replace(',', '', $limpio);
        } elseif (str_contains($limpio, ',')) {
            $limpio = preg_match('/,\d{3}$/', $limpio)
                ? str_replace(',', '', $limpio)
                : str_replace(',', '.', $limpio);
        } elseif (substr_count($limpio, '.') > 1 || preg_match('/\.\d{3}$/', $limpio)) {
            $limpio = str_replace('.', '', $limpio);
        }

        return is_numeric($limpio) ? (float) $limpio : null;
    }

    /**
     * Espacios colapsados y paréntesis cerrados ("Pistas (CUNAS" => "Pistas (CUNAS)").
     */
    private function limpiar(string $valor): string
    {
        $valor = trim(preg_replace('/\s+/u', ' ', $valor));
        $valor = preg_replace('/\s*\(\s*$/u', '', $valor);
        $valor = preg_replace('/(?<=[\p{L}\d])\(/u', ' (', $valor);

        if (substr_count($valor, '(') > substr_count($valor, ')')) {
            $valor .= ')';
        }

        return $valor;
    }

    /**
     * Texto en MAYÚSCULAS pasa a minúsculas (salvo códigos con números),
     * las marcas toman su forma canónica y la primera letra va en mayúscula.
     */
    private function formatear(string $texto): string
    {
        $todoMayusculas = mb_strtoupper($texto) === $texto && preg_match('/\p{Lu}{2,}/u', $texto);

        $resultado = preg_replace_callback('/[\p{L}\d&]+/u', function (array $coincidencia) use ($todoMayusculas) {
            $palabra = $coincidencia[0];
            $marca = self::MARCAS[mb_strtolower($palabra)] ?? null;

            if ($marca !== null) {
                return $marca;
            }

            if ($todoMayusculas && ! preg_match('/\d/', $palabra)) {
                return mb_strtolower($palabra);
            }

            return $palabra;
        }, $texto);

        return mb_strtoupper(mb_substr($resultado, 0, 1)) . mb_substr($resultado, 1);
    }

    private function corregir(string $texto): string
    {
        $texto = mb_strtolower($this->limpiar($texto));

        return preg_replace_callback('/\p{L}+/u', fn ($m) => self::CORRECCIONES[Str::ascii($m[0])] ?? $m[0], $texto);
    }

    private function quitarMarcas(string $texto): string
    {
        $patron = implode('|', array_map(fn ($m) => preg_quote($m, '/'), self::MARCAS));
        $sinMarcas = preg_replace("/\b({$patron})\b/iu", '', $texto);
        $sinMarcas = preg_replace('/\(\s*\)/u', '', $sinMarcas);

        return trim(preg_replace('/\s+/u', ' ', $sinMarcas));
    }

    private function marcaCanonica(string $texto): ?string
    {
        $clave = mb_strtolower(preg_replace('/[^\p{L}\d&]/u', '', $texto));

        return self::MARCAS[$clave] ?? null;
    }

    private function minusculaInicial(string $texto): string
    {
        $primera = strtok($texto, ' ');

        if ($this->marcaCanonica($primera) !== null) {
            return $texto;
        }

        return mb_strtolower(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
    }

    /**
     * Clave de comparación: sin tildes, minúsculas, solo letras/números y espacios.
     */
    private function clave(string $texto): string
    {
        $ascii = Str::lower(Str::ascii($texto));

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $ascii)));
    }

    /**
     * @param array<int, string> $celdas
     */
    private function esVacia(array $celdas): bool
    {
        // La columna L (total = stock × precio) no cuenta: viene en filas vacías como "0".
        foreach (array_slice($celdas, 0, self::COLUMNAS_DATOS) as $celda) {
            if ($celda !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, string> $celdas
     */
    private function tieneFormula(array $celdas): bool
    {
        foreach ($celdas as $celda) {
            if (str_starts_with($celda, '=')) {
                return true;
            }
        }

        return false;
    }
}
