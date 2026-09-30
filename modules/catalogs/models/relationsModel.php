<?php
/* Relaciones entre catálogos: Máquina×Área y Referencia×Máquina (por área) */

// Áreas activas
function areasOrdenadas($conexion)
{
    return mysqli_fetch_all(mysqli_query($conexion, "SELECT id_area, nombre_area FROM AREAS ORDER BY id_area"), MYSQLI_ASSOC);
}

// Máquinas activas por número
function maquinasOrdenadas($conexion)
{
    return mysqli_fetch_all(mysqli_query($conexion, "
        SELECT id_maquina, nombre_maquina
        FROM MAQUINAS
        WHERE estado = 1
        ORDER BY CAST(REGEXP_SUBSTR(nombre_maquina, '[0-9]+') AS UNSIGNED)
    "), MYSQLI_ASSOC);
}

// Máquinas de un área
function maquinasDeArea($conexion, $idArea)
{
    $idArea = (int) $idArea;
    return mysqli_fetch_all(mysqli_query($conexion, "
        SELECT m.id_maquina, m.nombre_maquina, ma.usa_referencias_esp
        FROM MAQUINAS m
        JOIN MAQUINA_AREAS ma ON ma.id_maquina = m.id_maquina
        WHERE m.estado = 1 AND ma.id_area = {$idArea}
        ORDER BY CAST(REGEXP_SUBSTR(m.nombre_maquina, '[0-9]+') AS UNSIGNED)
    "), MYSQLI_ASSOC);
}

// Matriz máquinas × áreas
function obtenerMatrizMaquinaAreas($conexion)
{
    $areas = areasOrdenadas($conexion);
    $relaciones = [];
    $res = mysqli_query($conexion, "SELECT id_maquina, id_area FROM MAQUINA_AREAS");
    while ($r = mysqli_fetch_assoc($res)) {
        $relaciones[$r['id_maquina']][$r['id_area']] = true;
    }
    return ['maquinas' => maquinasOrdenadas($conexion), 'areas' => $areas, 'relaciones' => $relaciones];
}

// Alternar máquina-área
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

// Matriz referencias × máquinas, para un área
function obtenerMatrizMaquinaReferencias($conexion, $idArea)
{
    $idArea = (int) $idArea;
    $referencias = mysqli_fetch_all(mysqli_query($conexion, "
        SELECT id_referencia, nombre_referencia FROM REFERENCIAS WHERE estado = 1
        ORDER BY CAST(REPLACE(REPLACE(nombre_referencia, ',', '.'), 'K', '') AS DECIMAL(10,2))
    "), MYSQLI_ASSOC);
    $relaciones = [];
    $res = mysqli_query($conexion, "SELECT id_maquina, id_referencia FROM MAQUINA_REFERENCIAS WHERE id_area = {$idArea}");
    while ($r = mysqli_fetch_assoc($res)) {
        $relaciones[$r['id_maquina']][$r['id_referencia']] = true;
    }
    return ['maquinas' => maquinasDeArea($conexion, $idArea), 'referencias' => $referencias, 'relaciones' => $relaciones];
}

// Alternar máquina-referencia (en un área)
function alternarMaquinaReferencia($conexion, $idMaquina, $idArea, $idReferencia)
{
    $idMaquina    = (int) $idMaquina;
    $idArea       = (int) $idArea;
    $idReferencia = (int) $idReferencia;
    $existe = mysqli_query($conexion, "SELECT 1 FROM MAQUINA_REFERENCIAS WHERE id_maquina = $idMaquina AND id_area = $idArea AND id_referencia = $idReferencia");
    if ($existe && mysqli_num_rows($existe) > 0) {
        return mysqli_query($conexion, "DELETE FROM MAQUINA_REFERENCIAS WHERE id_maquina = $idMaquina AND id_area = $idArea AND id_referencia = $idReferencia");
    }
    return mysqli_query($conexion, "INSERT INTO MAQUINA_REFERENCIAS (id_maquina, id_area, id_referencia) VALUES ($idMaquina, $idArea, $idReferencia)");
}

// Alternar Referencias Especiales (máquina + área)
function alternarUsaReferenciasEsp($conexion, $idMaquina, $idArea)
{
    $idMaquina = (int) $idMaquina;
    $idArea    = (int) $idArea;
    return mysqli_query($conexion, "UPDATE MAQUINA_AREAS SET usa_referencias_esp = IF(usa_referencias_esp = 1, 0, 1) WHERE id_maquina = $idMaquina AND id_area = $idArea");
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

// Exportar relaciones
function exportarRelaciones($conexion)
{
    $fecha = date('Y-m-d H:i');

    $lineas = ["-- Máquina x Área -- generado por Relaciones > Exportar el {$fecha}"];
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

    $lineas = ["-- Referencia x Máquina (por área) -- generado por Relaciones > Exportar el {$fecha}"];
    $res = mysqli_query($conexion, "
        SELECT m.nombre_maquina, a.nombre_area, r.nombre_referencia
        FROM MAQUINA_REFERENCIAS mr
        JOIN MAQUINAS m    ON m.id_maquina = mr.id_maquina
        JOIN AREAS a       ON a.id_area = mr.id_area
        JOIN REFERENCIAS r ON r.id_referencia = mr.id_referencia
        ORDER BY CAST(REGEXP_SUBSTR(m.nombre_maquina, '[0-9]+') AS UNSIGNED), a.id_area, r.id_referencia
    ");
    $totalRefs = 0;
    while ($f = mysqli_fetch_assoc($res)) {
        $lineas[] = "INSERT INTO MAQUINA_REFERENCIAS (maquina, area, referencia) VALUES ('" . escaparSeed($f['nombre_maquina']) . "', '" . escaparSeed($f['nombre_area']) . "', '" . escaparSeed($f['nombre_referencia']) . "');";
        $totalRefs++;
    }
    $res = mysqli_query($conexion, "
        SELECT m.nombre_maquina, a.nombre_area
        FROM MAQUINA_AREAS ma
        JOIN MAQUINAS m ON m.id_maquina = ma.id_maquina
        JOIN AREAS a    ON a.id_area = ma.id_area
        WHERE ma.usa_referencias_esp = 1
        ORDER BY CAST(REGEXP_SUBSTR(m.nombre_maquina, '[0-9]+') AS UNSIGNED), a.id_area
    ");
    $totalEsp = 0;
    while ($f = mysqli_fetch_assoc($res)) {
        $lineas[] = "UPDATE MAQUINA_AREAS SET usa_referencias_esp = 1 WHERE maquina = '" . escaparSeed($f['nombre_maquina']) . "' AND area = '" . escaparSeed($f['nombre_area']) . "';";
        $totalEsp++;
    }
    guardarSeedRelaciones('maquina_referencias', $lineas);

    return ['areas' => $totalAreas, 'referencias' => $totalRefs, 'especiales' => $totalEsp];
}

// Importar relaciones faltantes
function importarRelaciones($conexion)
{
    $rutaAreas = rutaSeedRelaciones('maquina_areas');
    $rutaRefs  = rutaSeedRelaciones('maquina_referencias');
    if (!is_file($rutaAreas) && !is_file($rutaRefs)) {
        return ['agregados' => 0, 'existentes' => 0, 'omitidos' => 0, 'error' => 'Todavía no se han exportado las relaciones (no existen los archivos semilla).'];
    }

    $agregados = $existentes = $omitidos = 0;

    // Máquina x Área
    if (is_file($rutaAreas)) {
        $patron = "/INSERT INTO MAQUINA_AREAS \\(maquina, area\\) VALUES\\s*\\('((?:[^']|'')*)'\\s*,\\s*'((?:[^']|'')*)'\\)/i";
        foreach (file($rutaAreas, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            if (!preg_match($patron, $linea, $m)) {
                continue;
            }
            $maquina = mysqli_real_escape_string($conexion, desescaparSeed($m[1]));
            $area    = mysqli_real_escape_string($conexion, desescaparSeed($m[2]));
            $ids = mysqli_fetch_assoc(mysqli_query($conexion, "
                SELECT (SELECT id_maquina FROM MAQUINAS WHERE nombre_maquina = '{$maquina}' LIMIT 1) AS id_m,
                       (SELECT id_area FROM AREAS WHERE nombre_area = '{$area}' LIMIT 1) AS id_a
            "));
            if (!$ids['id_m'] || !$ids['id_a']) {
                $omitidos++;
                continue;
            }
            $existe = mysqli_query($conexion, "SELECT 1 FROM MAQUINA_AREAS WHERE id_maquina = {$ids['id_m']} AND id_area = {$ids['id_a']}");
            if ($existe && mysqli_num_rows($existe) > 0) {
                $existentes++;
                continue;
            }
            mysqli_query($conexion, "INSERT INTO MAQUINA_AREAS (id_maquina, id_area) VALUES ({$ids['id_m']}, {$ids['id_a']})");
            $agregados++;
        }
    }

    // Referencia x Máquina (por área) + Especiales
    if (is_file($rutaRefs)) {
        $patronRef = "/INSERT INTO MAQUINA_REFERENCIAS \\(maquina, area, referencia\\) VALUES\\s*\\('((?:[^']|'')*)'\\s*,\\s*'((?:[^']|'')*)'\\s*,\\s*'((?:[^']|'')*)'\\)/i";
        $patronEsp = "/UPDATE MAQUINA_AREAS SET usa_referencias_esp = 1 WHERE maquina = '((?:[^']|'')*)' AND area = '((?:[^']|'')*)'/i";
        foreach (file($rutaRefs, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            if (preg_match($patronRef, $linea, $m)) {
                $maquina    = mysqli_real_escape_string($conexion, desescaparSeed($m[1]));
                $area       = mysqli_real_escape_string($conexion, desescaparSeed($m[2]));
                $referencia = mysqli_real_escape_string($conexion, desescaparSeed($m[3]));
                $ids = mysqli_fetch_assoc(mysqli_query($conexion, "
                    SELECT (SELECT id_maquina FROM MAQUINAS WHERE nombre_maquina = '{$maquina}' LIMIT 1) AS id_m,
                           (SELECT id_area FROM AREAS WHERE nombre_area = '{$area}' LIMIT 1) AS id_a,
                           (SELECT id_referencia FROM REFERENCIAS WHERE nombre_referencia = '{$referencia}' LIMIT 1) AS id_r
                "));
                if (!$ids['id_m'] || !$ids['id_a'] || !$ids['id_r']) {
                    $omitidos++;
                    continue;
                }
                $existe = mysqli_query($conexion, "SELECT 1 FROM MAQUINA_REFERENCIAS WHERE id_maquina = {$ids['id_m']} AND id_area = {$ids['id_a']} AND id_referencia = {$ids['id_r']}");
                if ($existe && mysqli_num_rows($existe) > 0) {
                    $existentes++;
                    continue;
                }
                mysqli_query($conexion, "INSERT INTO MAQUINA_REFERENCIAS (id_maquina, id_area, id_referencia) VALUES ({$ids['id_m']}, {$ids['id_a']}, {$ids['id_r']})");
                $agregados++;
                continue;
            }
            if (preg_match($patronEsp, $linea, $m)) {
                $maquina = mysqli_real_escape_string($conexion, desescaparSeed($m[1]));
                $area    = mysqli_real_escape_string($conexion, desescaparSeed($m[2]));
                mysqli_query($conexion, "
                    UPDATE MAQUINA_AREAS ma
                    JOIN MAQUINAS m ON m.id_maquina = ma.id_maquina
                    JOIN AREAS a    ON a.id_area = ma.id_area
                    SET ma.usa_referencias_esp = 1
                    WHERE m.nombre_maquina = '{$maquina}' AND a.nombre_area = '{$area}' AND ma.usa_referencias_esp = 0
                ");
                if (mysqli_affected_rows($conexion) > 0) {
                    $agregados++;
                } else {
                    $existentes++;
                }
            }
        }
    }

    return ['agregados' => $agregados, 'existentes' => $existentes, 'omitidos' => $omitidos, 'error' => null];
}
