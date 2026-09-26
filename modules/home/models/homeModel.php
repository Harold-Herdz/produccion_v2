<?php
require_once dirname(__DIR__, 2) . '/shared/systemState.php';
require_once dirname(__DIR__, 2) . '/catalogs/models/catalogsModel.php';

/* =====================================================
   MODELO DE INICIO (panel general)
   -----------------------------------------------------
   Define, por módulo, qué dimensiones (eje X / columnas de agrupación)
   y qué medidas (eje Y / columnas de datos) se pueden consultar, y arma
   las consultas agrupadas. El navegador solo envía CLAVES: todo el SQL
   sale de esta lista blanca.
===================================================== */

// Orden numérico de nombres
function homeOrdenNumerico($expr)
{
    return "CAST(REPLACE(REGEXP_SUBSTR({$expr}, '[0-9]+(,[0-9]+)?'), ',', '.') AS DECIMAL(12,2))";
}

// Módulos, dimensiones y medidas
function homeModulos()
{
    // Uniones reutilizables (alias => SQL)
    $uniones = [
        'm'  => 'LEFT JOIN MAQUINAS m ON m.id_maquina = p.id_maquina',
        'o'  => 'LEFT JOIN OPERARIOS o ON o.id_operario = p.id_operario',
        'r'  => 'LEFT JOIN REFERENCIAS r ON r.id_referencia = p.id_referencia',
        're' => 'LEFT JOIN REFERENCIAS_ESP re ON re.id_referencia_esp = p.id_referencia_esp',
        'c'  => 'LEFT JOIN COLORES c ON c.id_color = p.id_color',
        't'  => 'LEFT JOIN TURNOS t ON t.id_turno = p.id_turno',
        'j'  => 'LEFT JOIN JORNADAS j ON j.id_jornada = p.id_jornada',
        'od' => 'LEFT JOIN OPERADORES od ON od.id_operador = p.id_operador',
        'lp' => 'LEFT JOIN LAMINA_P lp ON lp.id_lamina_p = p.id_lamina_p',
    ];

    // Dimensiones de fecha
    $fechas = function ($col) {
        return [
            'fecha'  => ['etiqueta' => 'Fecha',  'expr' => "DATE_FORMAT(p.{$col}, '%Y-%m-%d')", 'orden' => "DATE_FORMAT(p.{$col}, '%Y-%m-%d')", 'joins' => [], 'tipo' => 'fecha'],
            'semana' => ['etiqueta' => 'Semana', 'expr' => "CONCAT(DATE_FORMAT(p.{$col}, '%Y-%m'), ' S', CEIL(DAY(p.{$col}) / 7))", 'orden' => "CONCAT(DATE_FORMAT(p.{$col}, '%Y-%m'), ' S', CEIL(DAY(p.{$col}) / 7))", 'joins' => [], 'tipo' => 'semana'],
            'mes'    => ['etiqueta' => 'Mes',    'expr' => "DATE_FORMAT(p.{$col}, '%Y-%m')", 'orden' => "DATE_FORMAT(p.{$col}, '%Y-%m')", 'joins' => [], 'tipo' => 'mes'],
        ];
    };
    $sd = "'(sin dato)'";
    $maquina    = ['etiqueta' => 'Máquina',    'expr' => "COALESCE(m.nombre_maquina, {$sd})", 'orden' => homeOrdenNumerico('m.nombre_maquina'), 'joins' => ['m']];
    $operario   = ['etiqueta' => 'Operario',   'expr' => "COALESCE(o.nombre_operario, {$sd})", 'orden' => "COALESCE(o.nombre_operario, {$sd})", 'joins' => ['o']];
    $color      = ['etiqueta' => 'Color',      'expr' => "COALESCE(c.nombre_color, {$sd})", 'orden' => "COALESCE(c.nombre_color, {$sd})", 'joins' => ['c']];
    $turno      = ['etiqueta' => 'Turno',      'expr' => "COALESCE(t.nombre_turno, {$sd})", 'orden' => 'COALESCE(t.id_turno, 99)', 'joins' => ['t']];
    $referencia = ['etiqueta' => 'Referencia', 'expr' => "COALESCE(r.nombre_referencia, {$sd})", 'orden' => homeOrdenNumerico('r.nombre_referencia'), 'joins' => ['r']];
    // Referencia normal o especial
    $referenciaMixta = ['etiqueta' => 'Referencia', 'expr' => "COALESCE(r.nombre_referencia, re.nombre_referencia_esp, {$sd})",
        'orden' => "COALESCE(r.nombre_referencia, re.nombre_referencia_esp, {$sd})", 'joins' => ['r', 're']];
    $referenciaEsp = ['etiqueta' => 'Referencia especial', 'expr' => "COALESCE(re.nombre_referencia_esp, {$sd})", 'orden' => "COALESCE(re.nombre_referencia_esp, {$sd})", 'joins' => ['re']];

    $modulos = [
        'sellado' => [
            'etiqueta'  => 'Sellado',
            'tabla'     => 'PRODUCCION_SELLADO',
            'fecha'     => 'fecha_sellado',
            'url'       => '/modules/sellado/views/dashboard.php',
            'uniones'   => $uniones,
            'dims'      => $fechas('fecha_sellado') + [
                'maquina' => $maquina, 'operario' => $operario, 'referencia' => $referenciaMixta,
                'color' => $color, 'turno' => $turno,
                'jornada' => ['etiqueta' => 'Jornada', 'expr' => "COALESCE(j.nombre_jornada, {$sd})", 'orden' => 'COALESCE(j.id_jornada, 99)', 'joins' => ['j']],
            ],
            'medidas'   => [
                'paquetes_total' => ['etiqueta' => 'Paquetes total', 'expr' => 'SUM(COALESCE(p.paquetes_total, 0))', 'eje' => 'y',  'color' => '#2f7ec2', 'defecto' => true],
                'paquetes_x70'   => ['etiqueta' => 'Paquetes x70',   'expr' => 'SUM(COALESCE(p.paquetes_x70, 0))',   'eje' => 'y',  'color' => '#3f9d5c', 'defecto' => false],
                'paquetes_x90'   => ['etiqueta' => 'Paquetes x90',   'expr' => 'SUM(COALESCE(p.paquetes_x90, 0))',   'eje' => 'y',  'color' => '#e0a020', 'defecto' => false],
                'paquetes_x98'   => ['etiqueta' => 'Paquetes x98',   'expr' => 'SUM(COALESCE(p.paquetes_x98, 0))',   'eje' => 'y',  'color' => '#a04ab8', 'defecto' => false],
            ],
            'principal' => 'paquetes_total',
            // Columnas de la tabla
            'columnas'  => ['paquetes_x70', 'paquetes_x90', 'paquetes_x98', 'paquetes_total'],
            'tabla_dims_defecto' => ['fecha', 'maquina'],
        ],
        'rollo' => [
            'etiqueta'  => 'Rollos',
            'tabla'     => 'PRODUCCION_ROLLO',
            'fecha'     => 'fecha_rollo',
            'url'       => '/modules/rollo/views/dashboard.php',
            'uniones'   => $uniones,
            'dims'      => $fechas('fecha_rollo') + [
                'maquina' => $maquina, 'operario' => $operario, 'referencia' => $referencia, 'color' => $color,
            ],
            'medidas'   => [
                'peso_total' => ['etiqueta' => 'Peso total (kg)', 'expr' => 'SUM(COALESCE(p.peso_total, 0))', 'eje' => 'y', 'color' => '#3f9d5c', 'defecto' => true],
                'peso_rollo' => ['etiqueta' => 'Peso rollo (kg)',  'expr' => 'SUM(COALESCE(p.peso_rollo, 0))', 'eje' => 'y', 'color' => '#2f7ec2', 'defecto' => false],
                'peso_retal' => ['etiqueta' => 'Peso retal (kg)',  'expr' => 'SUM(COALESCE(p.peso_retal, 0))', 'eje' => 'y', 'color' => '#c0392b', 'defecto' => false],
            ],
            'principal' => 'peso_total',
            'columnas'  => ['peso_rollo', 'peso_retal', 'peso_total'],
            'tabla_dims_defecto' => ['fecha', 'maquina'],
        ],
        'plana' => [
            'etiqueta'  => 'Máquina Plana',
            'tabla'     => 'PRODUCCION_PLANA',
            'fecha'     => 'fecha_plana',
            'url'       => '/modules/plana/views/dashboard.php',
            'uniones'   => $uniones,
            'dims'      => $fechas('fecha_plana') + [
                'maquina' => $maquina, 'operario' => $operario, 'referencia' => $referenciaEsp,
            ],
            'medidas'   => [
                'peso_total' => ['etiqueta' => 'Peso total (kg)', 'expr' => 'SUM(COALESCE(p.peso_total, 0))', 'eje' => 'y',  'color' => '#3f9d5c', 'defecto' => true],
                'bultos'     => ['etiqueta' => 'Bultos',          'expr' => 'SUM(COALESCE(p.bultos, 0))',     'eje' => 'y1', 'color' => '#e0a020', 'defecto' => true],
                'peso_rollo' => ['etiqueta' => 'Peso rollo (kg)', 'expr' => 'SUM(COALESCE(p.peso_rollo, 0))', 'eje' => 'y',  'color' => '#2f7ec2', 'defecto' => false],
                'peso_retal' => ['etiqueta' => 'Peso retal (kg)', 'expr' => 'SUM(COALESCE(p.peso_retal, 0))', 'eje' => 'y',  'color' => '#c0392b', 'defecto' => false],
            ],
            'principal' => 'peso_total',
            'columnas'  => ['peso_rollo', 'peso_retal', 'bultos', 'peso_total'],
            'tabla_dims_defecto' => ['fecha', 'maquina'],
        ],
        'extrusion' => [
            'etiqueta'  => 'Extrusión',
            'tabla'     => 'PRODUCCION_EXTRUSION',
            'fecha'     => 'fecha_extrusion',
            'url'       => '/modules/extrusion/views/dashboard.php',
            'uniones'   => $uniones,
            'dims'      => $fechas('fecha_extrusion') + [
                'maquina' => $maquina, 'turno' => $turno, 'referencia' => $referenciaMixta, 'color' => $color,
                'operador' => ['etiqueta' => 'Operador', 'expr' => "COALESCE(od.nombre_operador, {$sd})", 'orden' => "COALESCE(od.nombre_operador, {$sd})", 'joins' => ['od']],
                'lamina_p' => ['etiqueta' => 'Lámina P', 'expr' => "COALESCE(lp.nombre_lamina_p, {$sd})", 'orden' => "COALESCE(lp.nombre_lamina_p, {$sd})", 'joins' => ['lp']],
            ],
            'medidas'   => [
                'rollos'     => ['etiqueta' => 'Rollos',           'expr' => 'SUM(COALESCE(p.rollos, 0))',     'eje' => 'y',  'color' => '#2f7ec2', 'defecto' => true],
                'peso_total' => ['etiqueta' => 'Peso total (kg)', 'expr' => 'SUM(COALESCE(p.peso_total, 0))', 'eje' => 'y1', 'color' => '#3f9d5c', 'defecto' => true],
            ],
            'principal' => 'rollos',
            'columnas'  => ['rollos', 'peso_total'],
            'tabla_dims_defecto' => ['fecha', 'maquina'],
        ],
        'peletizado' => [
            'etiqueta'  => 'Peletizado',
            'tabla'     => 'PRODUCCION_PELETIZADO',
            'fecha'     => 'fecha_peletizado',
            'url'       => null, // aún no existe su módulo
            'uniones'   => $uniones,
            'dims'      => $fechas('fecha_peletizado') + [
                'maquina' => $maquina, 'turno' => $turno, 'operario' => $operario, 'color' => $color,
            ],
            'medidas'   => [
                'total'      => ['etiqueta' => 'Total',      'expr' => 'SUM(COALESCE(p.total, 0))',      'eje' => 'y', 'color' => '#3f9d5c', 'defecto' => true],
                'alta_retal' => ['etiqueta' => 'Alta retal', 'expr' => 'SUM(COALESCE(p.alta_retal, 0))', 'eje' => 'y', 'color' => '#2f7ec2', 'defecto' => false],
                'baja'       => ['etiqueta' => 'Baja',       'expr' => 'SUM(COALESCE(p.baja, 0))',       'eje' => 'y', 'color' => '#e0a020', 'defecto' => false],
                'refiltrado' => ['etiqueta' => 'Refiltrado', 'expr' => 'SUM(COALESCE(p.refiltrado, 0))', 'eje' => 'y', 'color' => '#a04ab8', 'defecto' => false],
                'soplado'    => ['etiqueta' => 'Soplado',    'expr' => 'SUM(COALESCE(p.soplado, 0))',    'eje' => 'y', 'color' => '#17a2b8', 'defecto' => false],
                'torta'      => ['etiqueta' => 'Torta',      'expr' => 'SUM(COALESCE(p.torta, 0))',      'eje' => 'y', 'color' => '#8d6e63', 'defecto' => false],
                'limpieza'   => ['etiqueta' => 'Limpieza',   'expr' => 'SUM(COALESCE(p.limpieza, 0))',   'eje' => 'y', 'color' => '#c0392b', 'defecto' => false],
            ],
            'principal' => 'total',
            'columnas'  => ['alta_retal', 'baja', 'refiltrado', 'soplado', 'torta', 'limpieza', 'total'],
            'tabla_dims_defecto' => ['fecha', 'maquina'],
        ],
    ];

    // Medidas en orden de columnas
    foreach ($modulos as $k => $m) {
        $modulos[$k]['medidas'] = array_replace(array_flip($m['columnas']), $m['medidas']);
    }
    return $modulos;
}

// Descripción para el navegador
function homeMeta()
{
    $meta = [];
    foreach (homeModulos() as $clave => $m) {
        $dims = [];
        foreach ($m['dims'] as $k => $d) {
            $dims[] = ['clave' => $k, 'etiqueta' => $d['etiqueta'], 'tipo' => $d['tipo'] ?? 'cat'];
        }
        $medidas = [];
        foreach ($m['medidas'] as $k => $md) {
            $medidas[] = ['clave' => $k, 'etiqueta' => $md['etiqueta'], 'eje' => $md['eje'], 'color' => $md['color'], 'defecto' => $md['defecto']];
        }
        $meta[$clave] = [
            'etiqueta' => $m['etiqueta'], 'url' => $m['url'], 'url_hoja' => homeUrlSheet($clave), 'url_pdfs' => homeCarpetaPdfs($clave), 'dims' => $dims, 'medidas' => $medidas,
            'columnas' => $m['columnas'], 'principal' => $m['principal'], 'tabla_dims_defecto' => $m['tabla_dims_defecto'],
        ];
    }
    return $meta;
}

// Fecha 'Y-m-d' válida o null
function homeFecha($valor)
{
    $valor = trim((string) $valor);
    $d = DateTime::createFromFormat('Y-m-d', $valor);
    return ($d && $d->format('Y-m-d') === $valor) ? $valor : null;
}

// FROM, uniones y WHERE
// $filtros: [claveDim => [valor, ...]]
function homeFromWhere($conexion, $mod, array $clavesDim, $desde, $hasta, array $filtros)
{
    $aliases = [];
    foreach (array_merge($clavesDim, array_keys($filtros)) as $k) {
        foreach ($mod['dims'][$k]['joins'] as $a) {
            $aliases[$a] = true;
        }
    }
    $sql = "FROM {$mod['tabla']} p";
    foreach (array_keys($aliases) as $a) {
        $sql .= ' ' . $mod['uniones'][$a];
    }

    $donde = ["p.{$mod['fecha']} IS NOT NULL"];
    if ($desde) { $donde[] = "p.{$mod['fecha']} >= '" . mysqli_real_escape_string($conexion, $desde) . "'"; }
    if ($hasta) { $donde[] = "p.{$mod['fecha']} <= '" . mysqli_real_escape_string($conexion, $hasta) . "'"; }
    foreach ($filtros as $k => $valores) {
        $lista = array_map(fn($v) => "'" . mysqli_real_escape_string($conexion, (string) $v) . "'", $valores);
        if ($lista) {
            $donde[] = "{$mod['dims'][$k]['expr']} IN (" . implode(', ', $lista) . ')';
        }
    }
    return $sql . ' WHERE ' . implode(' AND ', $donde);
}

// Consulta agrupada
function homeDatos($conexion, $clave, array $clavesDim, $desde, $hasta, array $filtros, $limite = 5000)
{
    $modulos = homeModulos();
    $mod = $modulos[$clave];

    $select = [];
    $grupo  = [];
    $orden  = [];
    foreach ($clavesDim as $i => $k) {
        $select[] = "{$mod['dims'][$k]['expr']} AS d{$i}";
        $select[] = "{$mod['dims'][$k]['orden']} AS o{$i}";
        $grupo[]  = "d{$i}, o{$i}";
        $orden[]  = "o{$i}, d{$i}";
    }
    foreach ($mod['medidas'] as $k => $md) {
        $select[] = "{$md['expr']} AS m_{$k}";
    }
    $select[] = 'COUNT(*) AS registros';

    $sql = 'SELECT ' . implode(', ', $select) . ' '
         . homeFromWhere($conexion, $mod, $clavesDim, $desde, $hasta, $filtros)
         . ($grupo ? ' GROUP BY ' . implode(', ', $grupo) . ' ORDER BY ' . implode(', ', $orden) : '')
         . ' LIMIT ' . (int) $limite;

    $filas = [];
    $res = mysqli_query($conexion, $sql);
    while ($res && ($f = mysqli_fetch_assoc($res))) {
        $fila = ['dims' => [], 'medidas' => [], 'registros' => (int) $f['registros']];
        foreach ($clavesDim as $i => $k) {
            $fila['dims'][$k] = $f["d{$i}"];
        }
        foreach ($mod['medidas'] as $k => $md) {
            $fila['medidas'][$k] = round((float) $f["m_{$k}"], 2);
        }
        $filas[] = $fila;
    }
    return $filas;
}

// Valores de una dimensión
function homeOpciones($conexion, $clave, $dim)
{
    $modulos = homeModulos();
    $mod = $modulos[$clave];
    $d = $mod['dims'][$dim];
    $sql = "SELECT {$d['expr']} AS v, {$d['orden']} AS o "
         . homeFromWhere($conexion, $mod, [$dim], null, null, [])
         . ' GROUP BY v, o ORDER BY o, v LIMIT 1000';
    $valores = [];
    $res = mysqli_query($conexion, $sql);
    while ($res && ($f = mysqli_fetch_assoc($res))) {
        $valores[] = $f['v'];
    }
    return $valores;
}

// Totales del módulo
function homeResumen($conexion, $clave, $desde, $hasta)
{
    $filas = homeDatos($conexion, $clave, [], $desde, $hasta, [], 1);
    $f = $filas[0] ?? ['medidas' => [], 'registros' => 0];
    return ['registros' => $f['registros'], 'medidas' => $f['medidas']];
}

/* =====================================================
   HOJAS DE GOOGLE (solo lectura desde el panel)
===================================================== */
function homeHojas()
{
    return [
        'sellado'   => ['id' => '1B1A-pSUBLG9w56ibWcERxhAKEsaPJN74SjRjeNv2UCg', 'gid_registros' => '1191265238'],
        'rollo'     => ['id' => '1LtibtaYF6GEsXE5Mxgq6uq8BR_ZEQ1idlqFUof5mgRo', 'gid_registros' => '46026898'],
        'plana'     => ['id' => '1DO_G6MHfoMagMMEOUOipTiE6W1UC-65f7BamJZQwGSc', 'gid_registros' => '1759801026'],
        'extrusion' => ['id' => '1TLsQx_s9tWBjJwuPm9xseJfsDQbKDQNQKOK9lf9Xezk', 'gid_registros' => '1284283091', 'gid_import' => '1583688034'],
    ];
}

// Enlace al Sheet (Lector no edita)
function homeUrlSheet($modulo)
{
    $hojas = homeHojas();
    if (!isset($hojas[$modulo])) {
        return null;
    }
    return "https://docs.google.com/spreadsheets/d/{$hojas[$modulo]['id']}/edit?gid={$hojas[$modulo]['gid_registros']}#gid={$hojas[$modulo]['gid_registros']}";
}

// Carpeta de PDFs (vacío = sin botón)
function homeCarpetaPdfs($modulo)
{
    $carpetas = [
        'sellado'   => 'https://drive.google.com/drive/folders/1YPCqkkM6EqUcPaHFJdYxh_oDqwBZJioO?usp=sharing',
        'rollo'     => 'https://drive.google.com/drive/folders/1GB7rr6TarxERg0TfA7SfF8-Xb7phbSyw?usp=sharing',
        'plana'     => 'https://drive.google.com/drive/folders/192yKfIMnbpEb2i8DRMw2cOdwTVCp9wvw?usp=sharing',
        'extrusion' => 'https://drive.google.com/drive/folders/1x9vnygja6Pv4S9pA8tbm-1sGzM75-m2b?usp=sharing',
    ];
    return ($carpetas[$modulo] ?? '') ?: null;
}

// URL CSV de REGISTROS o LOGS
function homeUrlHoja($modulo, $hoja)
{
    $hojas = homeHojas();
    if (!isset($hojas[$modulo])) {
        return null;
    }
    $h = $hojas[$modulo];
    // Hoja que lee el importador (en Extrusión es RESUMEN)
    if ($hoja === 'IMPORT') {
        return "https://docs.google.com/spreadsheets/d/{$h['id']}/export?format=csv&gid=" . ($h['gid_import'] ?? $h['gid_registros']);
    }
    if ($hoja === 'REGISTROS') {
        return "https://docs.google.com/spreadsheets/d/{$h['id']}/export?format=csv&gid={$h['gid_registros']}";
    }
    if ($hoja === 'LOGS') {
        return "https://docs.google.com/spreadsheets/d/{$h['id']}/gviz/tq?tqx=out:csv&sheet=LOGS";
    }
    return null;
}

// Filas de una hoja
function homeLeerHoja($modulo, $hoja, $maxFilas = 30000)
{
    $url = homeUrlHoja($modulo, $hoja);
    if (!$url) {
        return null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 25]]);
    $fh = @fopen($url, 'r', false, $ctx);
    if (!$fh) {
        return null;
    }
    $filas = [];
    while (($f = fgetcsv($fh, 0, ',')) !== false && count($filas) < $maxFilas) {
        $filas[] = $f;
    }
    fclose($fh);
    return $filas ?: null;
}

/* =====================================================
   RESUMEN Y ESTADO POR MÓDULO
===================================================== */
// Tablas de seguimiento
function homeSeguimiento()
{
    return [
        'sellado'   => ['tabla' => 'SELLADO_SHEET',   'abierto' => "estado = 'abierta'",    'cerrado' => "estado = 'finalizada'", 'etq_abierto' => 'Planillas abiertas', 'etq_cerrado' => 'Planillas finalizadas'],
        'rollo'     => ['tabla' => 'ROLLO_SHEET',     'abierto' => "estado = 'en_proceso'", 'cerrado' => "estado = 'completado'", 'etq_abierto' => 'Días en proceso',     'etq_cerrado' => 'Días cerrados'],
        'plana'     => ['tabla' => 'PLANA_SHEET',     'abierto' => "estado = 'en_proceso'", 'cerrado' => "estado = 'completado'", 'etq_abierto' => 'Días en proceso',     'etq_cerrado' => 'Días cerrados'],
        'extrusion' => ['tabla' => 'EXTRUSION_SHEET', 'abierto' => "estado = 'abierta'",    'cerrado' => "estado = 'finalizada'", 'etq_abierto' => 'Planillas abiertas', 'etq_cerrado' => 'Planillas finalizadas'],
    ];
}

// Resumen y actividad del rango
function homeResumenModulo($conexion, $clave, $desde, $hasta)
{
    $modulos = homeModulos();
    $mod = $modulos[$clave];
    $r = homeResumen($conexion, $clave, $desde, $hasta);

    $where = homeFromWhere($conexion, $mod, [], $desde, $hasta, []);
    $sql = "SELECT COUNT(DISTINCT p.id_maquina) AS maquinas, COUNT(DISTINCT p.{$mod['fecha']}) AS dias {$where}";
    $extra = mysqli_fetch_assoc(mysqli_query($conexion, $sql)) ?: [];
    $r['maquinas'] = (int) ($extra['maquinas'] ?? 0);
    $r['dias']     = (int) ($extra['dias'] ?? 0);
    return $r;
}

// Estado del módulo
function homeEstadoModulo($conexion, $clave)
{
    $modulos = homeModulos();
    $mod = $modulos[$clave];
    $estado = estadoSistemaLeer();

    $fila = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS total, MAX(p.{$mod['fecha']}) AS ultimo FROM {$mod['tabla']} p")) ?: [];
    $out = [
        'total_registros' => (int) ($fila['total'] ?? 0),
        'ultimo_registro' => $fila['ultimo'] ?? null,
        'importa'         => isset(homeHojas()[$clave]),
    ];

    $area = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT ultimo_id_sheet FROM AREAS WHERE nombre_area = '" . mysqli_real_escape_string($conexion, $clave) . "'"));
    $out['ultimo_id_sheet'] = $area['ultimo_id_sheet'] ?? null;
    $imp = $estado['importaciones'][$clave] ?? null;
    $out['ultima_importacion'] = $imp['fecha'] ?? null;
    $out['ultima_importacion_detalle'] = $imp ? "{$imp['insertados']} nuevos, {$imp['actualizados']} actualizados" : null;

    $seg = homeSeguimiento()[$clave] ?? null;
    $out['seguimiento'] = [];
    if ($seg) {
        $ab = mysqli_fetch_row(mysqli_query($conexion, "SELECT COUNT(*) FROM {$seg['tabla']} WHERE {$seg['abierto']}"));
        $ce = mysqli_fetch_row(mysqli_query($conexion, "SELECT COUNT(*) FROM {$seg['tabla']} WHERE {$seg['cerrado']}"));
        $sp = mysqli_fetch_row(mysqli_query($conexion, "SELECT COUNT(*) FROM {$seg['tabla']} WHERE {$seg['cerrado']} AND (ruta_pdf IS NULL OR ruta_pdf = '')"));
        $out['seguimiento'] = [
            ['etiqueta' => $seg['etq_abierto'], 'valor' => (int) $ab[0]],
            ['etiqueta' => $seg['etq_cerrado'], 'valor' => (int) $ce[0]],
            ['etiqueta' => 'Cerrados sin PDF', 'valor' => (int) $sp[0], 'alerta' => (int) $sp[0] > 0],
        ];
    }
    return $out;
}

// Registros pendientes de importar
function homePendientes($conexion, $clave, $forzar = false)
{
    if (!isset(homeHojas()[$clave])) {
        return ['disponible' => false];
    }
    $estado = estadoSistemaLeer();
    $cache = $estado['pendientes'][$clave] ?? null;
    if (!$forzar && $cache && (time() - (int) ($cache['ts'] ?? 0)) < 300) {
        return ['disponible' => true, 'pendientes' => (int) $cache['n']];
    }

    $filas = homeLeerHoja($clave, 'IMPORT', 60000);
    if ($filas === null) {
        return ['disponible' => true, 'pendientes' => null, 'error' => 'No se pudo leer el Sheet'];
    }
    $area = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT ultimo_id_sheet FROM AREAS WHERE nombre_area = '" . mysqli_real_escape_string($conexion, $clave) . "'"));
    $ultimo = $area['ultimo_id_sheet'] ?? null;
    $n = 0;
    foreach (array_slice($filas, 1) as $f) {
        $id = trim($f[0] ?? '');
        if ($id === '') {
            continue;
        }
        if (!$ultimo || strcmp($id, $ultimo) > 0) {
            $n++;
        }
    }
    estadoSistemaGuardar('pendientes', $clave, ['n' => $n, 'ts' => time()]);
    return ['disponible' => true, 'pendientes' => $n];
}

/* =====================================================
   RESUMEN GENERAL Y CATÁLOGOS
===================================================== */
// Tarjetas superiores
function homeGeneral($conexion)
{
    $desde = date('Y-m-01');
    $hasta = date('Y-m-d');
    $mods = [];
    $totalRegistros = 0;
    foreach (array_keys(homeModulos()) as $k) {
        $mods[$k] = [
            'mes'    => homeResumen($conexion, $k, $desde, $hasta),
            'estado' => homeEstadoModulo($conexion, $k),
        ];
        $totalRegistros += $mods[$k]['estado']['total_registros'];
    }
    $pend = mysqli_fetch_row(mysqli_query($conexion, "SELECT COUNT(*) FROM CATALOGO_PENDIENTES WHERE decision = 'pendiente'"));
    return ['modulos' => $mods, 'total_registros' => $totalRegistros, 'notificaciones' => (int) ($pend[0] ?? 0)];
}

// Estado de catálogos
function homeCatalogos($conexion)
{
    $estado = estadoSistemaLeer();
    $filas = [];
    $sinExportar = 0;
    foreach (catalogosDisponibles() as $k => $cfg) {
        $t = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS total, COALESCE(SUM(estado = 1), 0) AS activos FROM {$cfg['tabla']}")) ?: [];
        $ruta = rutaSeedCatalogo($cfg);
        $alDia = catalogoAlDia($conexion, $cfg);
        if (!$alDia) {
            $sinExportar++;
        }
        $e = $estado['catalogos'][strtolower($cfg['tabla'])] ?? [];
        $filas[] = [
            'clave'     => $k,
            'etiqueta'  => $cfg['etiqueta'],
            'total'     => (int) ($t['total'] ?? 0),
            'activos'   => (int) ($t['activos'] ?? 0),
            'exportado' => is_file($ruta) ? date('Y-m-d H:i:s', filemtime($ruta)) : null,
            'importado' => $e['importado'] ?? null,
            'al_dia'    => $alDia,
        ];
    }
    return ['catalogos' => $filas, 'sin_exportar' => $sinExportar];
}
