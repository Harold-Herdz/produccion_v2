<?php
/* =====================================================
   MODELO DE RELACIONES
   -----------------------------------------------------
   Relaciones entre catálogos:
     - Máquina × Área        (MAQUINA_AREAS)
     - Máquina × Referencia  (MAQUINA_REFERENCIAS) y la marca "Especiales"
       (MAQUINAS.usa_referencias_esp)
   Se exportan/importan POR NOMBRE (no por id) para que sobrevivan a
   una reimportación de los catálogos.
===================================================== */

// Máquinas activas en orden por número real (no por id)
function maquinasOrdenadas($conexion)
{
    return mysqli_fetch_all(mysqli_query($conexion, "
        SELECT id_maquina, nombre_maquina, usa_referencias_esp
        FROM MAQUINAS
        WHERE estado = 1
        ORDER BY CAST(REGEXP_SUBSTR(nombre_maquina, '[0-9]+') AS UNSIGNED)
    "), MYSQLI_ASSOC);
}

// Máquinas x Áreas: qué máquinas están habilitadas en cada módulo
function obtenerMatrizMaquinaAreas($conexion)
{
    $areas = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_area, nombre_area FROM AREAS ORDER BY id_area"), MYSQLI_ASSOC);
    $relaciones = [];
    $res = mysqli_query($conexion, "SELECT id_maquina, id_area FROM MAQUINA_AREAS");
    while ($r = mysqli_fetch_assoc($res)) {
        $relaciones[$r['id_maquina']][$r['id_area']] = true;
    }
    return ['maquinas' => maquinasOrdenadas($conexion), 'areas' => $areas, 'relaciones' => $relaciones];
}

// Alterna si una máquina pertenece a un área
function alternarMaquinaArea($conexion, $idMaquina, $idArea)
{
    $idMaquina = (int) $idMaquina;
    $idArea    = (int) $idArea;
    $existe = mysqli_query($conexion, "SELECT 1 FROM MAQUINA_AREAS WHERE id_maquina = $idMaquina AND id_area = $idArea");
    if ($existe && mysqli_num_rows($existe) > 0) {
        return mysqli_query($conexion, "DELETE FROM MAQUINA_AREAS WHERE id_maquina = $idMaquina AND id_area = $idArea");
    }
    return mysqli_query($conexion, "INSERT INTO MAQUINA_AREAS (id_maquina, id_area) VALUES ($idMaquina, $idArea)");
}

// Máquinas x Referencias: qué referencias normales produce cada máquina
function obtenerMatrizMaquinaReferencias($conexion)
{
    $referencias = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_referencia, nombre_referencia FROM REFERENCIAS WHERE estado = 1 ORDER BY id_referencia"), MYSQLI_ASSOC);
    $relaciones = [];
    $res = mysqli_query($conexion, "SELECT id_maquina, id_referencia FROM MAQUINA_REFERENCIAS");
    while ($r = mysqli_fetch_assoc($res)) {
        $relaciones[$r['id_maquina']][$r['id_referencia']] = true;
    }
    return ['maquinas' => maquinasOrdenadas($conexion), 'referencias' => $referencias, 'relaciones' => $relaciones];
}

// Alterna si una máquina produce una referencia
function alternarMaquinaReferencia($conexion, $idMaquina, $idReferencia)
{
    $idMaquina    = (int) $idMaquina;
    $idReferencia = (int) $idReferencia;
    $existe = mysqli_query($conexion, "SELECT 1 FROM MAQUINA_REFERENCIAS WHERE id_maquina = $idMaquina AND id_referencia = $idReferencia");
    if ($existe && mysqli_num_rows($existe) > 0) {
        return mysqli_query($conexion, "DELETE FROM MAQUINA_REFERENCIAS WHERE id_maquina = $idMaquina AND id_referencia = $idReferencia");
    }
    return mysqli_query($conexion, "INSERT INTO MAQUINA_REFERENCIAS (id_maquina, id_referencia) VALUES ($idMaquina, $idReferencia)");
}

// Alterna si una máquina usa el catálogo completo de Referencias Especiales ("Bolsa Basura")
function alternarUsaReferenciasEsp($conexion, $idMaquina)
{
    $idMaquina = (int) $idMaquina;
    return mysqli_query($conexion, "UPDATE MAQUINAS SET usa_referencias_esp = IF(usa_referencias_esp = 1, 0, 1) WHERE id_maquina = $idMaquina");
}

/* =====================================================
   EXPORTAR / IMPORTAR (archivos semilla, por nombre)
===================================================== */
function rutaSeedRelaciones($nombre)
{
    return dirname(__DIR__) . '/seed/' . $nombre . '.sql';
}

function escaparSeed($texto)
{
    return str_replace("'", "''", $texto);
}

function desescaparSeed($texto)
{
    return str_replace("''", "'", $texto);
}

function guardarSeedRelaciones($nombre, array $lineas)
{
    $dir = dirname(__DIR__) . '/seed';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents(rutaSeedRelaciones($nombre), implode("\n", $lineas) . "\n");
}

// Guarda Máquina × Área y Máquina × Referencia (+ Especiales). Devuelve los totales.
function exportarRelaciones($conexion)
{
    $fecha = date('Y-m-d H:i');

    $lineas = ["-- Máquina x Área (MAQUINA_AREAS) -- generado por Relaciones > Exportar el {$fecha}"];
    $res = mysqli_query($conexion, "
        SELECT m.nombre_maquina, a.nombre_area
        FROM MAQUINA_AREAS ma
        JOIN MAQUINAS m ON m.id_maquina = ma.id_maquina
        JOIN AREAS a    ON a.id_area = ma.id_area
        ORDER BY CAST(REGEXP_SUBSTR(m.nombre_maquina, '[0-9]+') AS UNSIGNED), a.id_area
    ");
    $totalAreas = 0;
    while ($f = mysqli_fetch_assoc($res)) {
        $lineas[] = "INSERT INTO MAQUINA_AREAS (maquina, area) VALUES ('" . escaparSeed($f['nombre_maquina']) . "', '" . escaparSeed($f['nombre_area']) . "');";
        $totalAreas++;
    }
    guardarSeedRelaciones('maquina_areas', $lineas);

    $lineas = ["-- Máquina x Referencia (MAQUINA_REFERENCIAS) y Especiales -- generado por Relaciones > Exportar el {$fecha}"];
    $res = mysqli_query($conexion, "
        SELECT m.nombre_maquina, r.nombre_referencia
        FROM MAQUINA_REFERENCIAS mr
        JOIN MAQUINAS m    ON m.id_maquina = mr.id_maquina
        JOIN REFERENCIAS r ON r.id_referencia = mr.id_referencia
        ORDER BY CAST(REGEXP_SUBSTR(m.nombre_maquina, '[0-9]+') AS UNSIGNED), r.id_referencia
    ");
    $totalRefs = 0;
    while ($f = mysqli_fetch_assoc($res)) {
        $lineas[] = "INSERT INTO MAQUINA_REFERENCIAS (maquina, referencia) VALUES ('" . escaparSeed($f['nombre_maquina']) . "', '" . escaparSeed($f['nombre_referencia']) . "');";
        $totalRefs++;
    }
    $res = mysqli_query($conexion, "
        SELECT nombre_maquina FROM MAQUINAS WHERE usa_referencias_esp = 1
        ORDER BY CAST(REGEXP_SUBSTR(nombre_maquina, '[0-9]+') AS UNSIGNED)
    ");
    $totalEsp = 0;
    while ($f = mysqli_fetch_assoc($res)) {
        $lineas[] = "UPDATE MAQUINAS SET usa_referencias_esp = 1 WHERE nombre_maquina = '" . escaparSeed($f['nombre_maquina']) . "';";
        $totalEsp++;
    }
    guardarSeedRelaciones('maquina_referencias', $lineas);

    return ['areas' => $totalAreas, 'referencias' => $totalRefs, 'especiales' => $totalEsp];
}

// Agrega desde los archivos semilla las relaciones que falten (nunca quita ninguna).
// Devuelve ['agregados' => n, 'existentes' => n, 'omitidos' => n, 'error' => ?string]
function importarRelaciones($conexion)
{
    $rutaAreas = rutaSeedRelaciones('maquina_areas');
    $rutaRefs  = rutaSeedRelaciones('maquina_referencias');
    if (!is_file($rutaAreas) && !is_file($rutaRefs)) {
        return ['agregados' => 0, 'existentes' => 0, 'omitidos' => 0, 'error' => 'Todavía no se han exportado las relaciones (no existen los archivos semilla).'];
    }

    $agregados = $existentes = $omitidos = 0;
    $par = "VALUES\\s*\\('((?:[^']|'')*)'\\s*,\\s*'((?:[^']|'')*)'\\)";

    // Máquina × Área / Máquina × Referencia: se resuelven por nombre
    $tareas = [
        [$rutaAreas, "/INSERT INTO MAQUINA_AREAS \\(maquina, area\\) {$par}/i",
            'MAQUINA_AREAS', 'id_area', 'AREAS', 'id_area', 'nombre_area'],
        [$rutaRefs, "/INSERT INTO MAQUINA_REFERENCIAS \\(maquina, referencia\\) {$par}/i",
            'MAQUINA_REFERENCIAS', 'id_referencia', 'REFERENCIAS', 'id_referencia', 'nombre_referencia'],
    ];
    foreach ($tareas as [$ruta, $patron, $tablaRel, $colRel, $tablaCat, $colIdCat, $colNombreCat]) {
        if (!is_file($ruta)) {
            continue;
        }
        foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            if (!preg_match($patron, $linea, $m)) {
                continue;
            }
            $maquina = mysqli_real_escape_string($conexion, desescaparSeed($m[1]));
            $otro    = mysqli_real_escape_string($conexion, desescaparSeed($m[2]));

            $ids = mysqli_fetch_assoc(mysqli_query($conexion, "
                SELECT (SELECT id_maquina FROM MAQUINAS WHERE nombre_maquina = '{$maquina}' LIMIT 1) AS id_m,
                       (SELECT {$colIdCat} FROM {$tablaCat} WHERE {$colNombreCat} = '{$otro}' LIMIT 1) AS id_o
            "));
            if (!$ids['id_m'] || !$ids['id_o']) {
                $omitidos++; // la máquina/área/referencia ya no existe en el catálogo
                continue;
            }
            $existe = mysqli_query($conexion, "SELECT 1 FROM {$tablaRel} WHERE id_maquina = {$ids['id_m']} AND {$colRel} = {$ids['id_o']}");
            if ($existe && mysqli_num_rows($existe) > 0) {
                $existentes++;
                continue;
            }
            mysqli_query($conexion, "INSERT INTO {$tablaRel} (id_maquina, {$colRel}) VALUES ({$ids['id_m']}, {$ids['id_o']})");
            $agregados++;
        }
    }

    // Especiales (Bolsa Basura)
    if (is_file($rutaRefs)) {
        foreach (file($rutaRefs, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            if (!preg_match("/UPDATE MAQUINAS SET usa_referencias_esp = 1 WHERE nombre_maquina = '((?:[^']|'')*)'/i", $linea, $m)) {
                continue;
            }
            $maquina = mysqli_real_escape_string($conexion, desescaparSeed($m[1]));
            mysqli_query($conexion, "UPDATE MAQUINAS SET usa_referencias_esp = 1 WHERE nombre_maquina = '{$maquina}' AND usa_referencias_esp = 0");
            if (mysqli_affected_rows($conexion) > 0) {
                $agregados++;
            } else {
                $existentes++;
            }
        }
    }

    return ['agregados' => $agregados, 'existentes' => $existentes, 'omitidos' => $omitidos, 'error' => null];
}
