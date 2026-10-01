<?php
// Modelo de la planilla de Peletizado

date_default_timezone_set('America/Bogota');

require_once dirname(__DIR__, 2) . '/shared/catalogosModel.php';

/* =================================================
   TURNOS Y CÓDIGOS
================================================= */
function turnosPeletizado(){
    return ['Día' => '01', 'Noche' => '02', '18 Horas' => '03'];
}

// Código del turno: P{yyyyMMdd}_T{01|02|03}
function construirCodigoPeletizado($fecha, $nombreTurno){
    $cod = turnosPeletizado()[$nombreTurno] ?? '00';
    return 'P' . date('Ymd', strtotime($fecha)) . '_T' . $cod;
}

// Id del día en LOGS (sin el turno)
function idDiaPeletizado($fecha){
    return 'P' . date('Ymd', strtotime($fecha));
}

// Nombre del PDF: P{yyyyMMdd}_P{máquina}
function nombrePdfPeletizado($fecha, $nombreMaquina){
    $num = (int) preg_replace('/\D/', '', $nombreMaquina);
    return 'P' . date('Ymd', strtotime($fecha)) . '_P' . str_pad($num, 2, '0', STR_PAD_LEFT) . '.pdf';
}

// Validar fecha Y-m-d
function validarFechaPeletizado($fecha){
    $fecha = trim((string) $fecha);
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return ($d && $d->format('Y-m-d') === $fecha) ? $fecha : null;
}

/* =================================================
   CATÁLOGOS DEL FORMULARIO
================================================= */
// Máquinas del área peletizado
function maquinasPeletizado($conexion){
    return $conexion->query("
        SELECT m.id_maquina, m.nombre_maquina
        FROM maquinas m
        JOIN maquina_areas ma ON ma.id_maquina = m.id_maquina
        JOIN areas a ON a.id_area = ma.id_area
        WHERE m.estado = 1 AND a.nombre_area = 'peletizado'
        ORDER BY CAST(REGEXP_SUBSTR(m.nombre_maquina, '[0-9]+') AS UNSIGNED)
    ")->fetch_all(MYSQLI_ASSOC);
}

function turnosCatalogoPeletizado($conexion){
    $res = $conexion->query("
        SELECT id_turno, nombre_turno FROM turnos
        WHERE estado = 1 AND nombre_turno IN ('Día', 'Noche', '18 Horas')
        ORDER BY FIELD(nombre_turno, 'Día', 'Noche', '18 Horas')
    ");
    return $res->fetch_all(MYSQLI_ASSOC);
}

function operariosPeletizado($conexion){
    return $conexion->query("SELECT id_operario, nombre_operario FROM operarios WHERE estado = 1 ORDER BY nombre_operario")->fetch_all(MYSQLI_ASSOC);
}

// Mapas id => nombre
function mapasCatalogoPeletizado($conexion){
    $mapa = function(array $filas, $id, $nombre){
        $m = [];
        foreach($filas as $f){ $m[(string) $f[$id]] = $f[$nombre]; }
        return $m;
    };
    return [
        'operario' => $mapa(operariosPeletizado($conexion), 'id_operario', 'nombre_operario'),
        'color'    => $mapa(obtenerColoresOrdenados($conexion), 'id_color', 'nombre_color'),
    ];
}

/* =================================================
   CONSULTAS DE PLANILLAS
================================================= */
function sqlPlanillaPeletizado(){
    return "SELECT p.*, m.nombre_maquina, t.nombre_turno
            FROM peletizado_sheet p
            JOIN maquinas m ON m.id_maquina = p.id_maquina
            JOIN turnos t ON t.id_turno = p.id_turno";
}

function obtenerPlanillaPeletizado($conexion, $id){
    $stmt = $conexion->prepare(sqlPlanillaPeletizado() . " WHERE p.id_planilla = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function buscarPlanillaPeletizado($conexion, $codigo, $idMaquina){
    $stmt = $conexion->prepare(sqlPlanillaPeletizado() . " WHERE p.codigo = ? AND p.id_maquina = ? LIMIT 1");
    $stmt->bind_param('si', $codigo, $idMaquina);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Planillas abiertas (para continuar)
function planillasAbiertasPeletizado($conexion){
    return $conexion->query(sqlPlanillaPeletizado() . " WHERE p.estado = 'abierta' ORDER BY p.creado_en DESC")->fetch_all(MYSQLI_ASSOC);
}

// Finalizadas por máquina y fecha (para armar el PDF del día completo)
function planillasFinalizadasPeletizado($conexion, $idMaquina, $fecha){
    $stmt = $conexion->prepare(sqlPlanillaPeletizado() . " WHERE p.id_maquina = ? AND p.fecha_planilla = ? AND p.estado = 'finalizada'
        ORDER BY FIELD(t.nombre_turno, 'Día', 'Noche', '18 Horas')");
    $stmt->bind_param('is', $idMaquina, $fecha);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function crearPlanillaPeletizado($conexion, $fecha, $idTurno, $nombreTurno, $idMaquina){
    $codigo = construirCodigoPeletizado($fecha, $nombreTurno);
    $stmt = $conexion->prepare("INSERT INTO peletizado_sheet (codigo, fecha_planilla, id_maquina, id_turno) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssii', $codigo, $fecha, $idMaquina, $idTurno);
    $stmt->execute();
    return obtenerPlanillaPeletizado($conexion, $conexion->insert_id);
}

function cancelarPlanillaPeletizado($conexion, $id){
    $stmt = $conexion->prepare("DELETE FROM peletizado_sheet WHERE id_planilla = ? AND estado = 'abierta'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
}

// Guardar borrador (JSON: operarios, colores y observaciones)
function guardarBorradorPeletizado($conexion, $id, $json){
    $stmt = $conexion->prepare("UPDATE peletizado_sheet SET filas = ? WHERE id_planilla = ? AND estado = 'abierta'");
    $stmt->bind_param('si', $json, $id);
    $stmt->execute();
}

function cerrarPlanillaPeletizado($conexion, $id, $total, $rutaPdf, $jsonFinal){
    $stmt = $conexion->prepare("UPDATE peletizado_sheet SET estado = 'finalizada', total_registros = ?, ruta_pdf = ?, filas = ?, finalizado_en = NOW() WHERE id_planilla = ?");
    $stmt->bind_param('issi', $total, $rutaPdf, $jsonFinal, $id);
    $stmt->execute();
}

/* =================================================
   BORRADOR (operarios + filas de color + observaciones)
================================================= */
// Limpiar borrador del navegador
function limpiarBorradorPeletizado($entrada){
    $entrada = (array) $entrada;
    $colores = [];
    foreach((array) ($entrada['colores'] ?? []) as $c){
        if(!is_array($c)){ continue; }
        $colores[] = [
            'color'      => mb_substr((string) ($c['color'] ?? ''), 0, 70),
            'alta_retal' => substr(trim((string) ($c['alta_retal'] ?? '')), 0, 10),
            'baja'       => substr(trim((string) ($c['baja'] ?? '')), 0, 10),
            'refiltrado' => substr(trim((string) ($c['refiltrado'] ?? '')), 0, 10),
            'soplado'    => substr(trim((string) ($c['soplado'] ?? '')), 0, 10),
            'torta'      => substr(trim((string) ($c['torta'] ?? '')), 0, 10),
            'limpieza'   => substr(trim((string) ($c['limpieza'] ?? '')), 0, 10),
        ];
    }
    return [
        'id_operario'  => mb_substr(trim((string) ($entrada['id_operario'] ?? '')), 0, 80),
        'id_operario2' => mb_substr(trim((string) ($entrada['id_operario2'] ?? '')), 0, 80),
        'colores'      => array_slice($colores, 0, 6),
        'observaciones'=> mb_substr(trim((string) ($entrada['observaciones'] ?? '')), 0, 500),
    ];
}

// Entero >= 0; vacío = 0; inválido o negativo = null (error)
function enteroPeletizado($valor){
    $valor = trim((string) $valor);
    if($valor === ''){
        return 0;
    }
    if(!is_numeric($valor) || (float) $valor < 0){
        return null;
    }
    return (int) round((float) $valor);
}

// Nombre de un valor "Otro": usa el existente o lo crea (solo al finalizar)
function resolverLibrePeletizado($conexion, $tabla, $colId, $colNombre, $etiqueta, $texto, array &$cache){
    $texto = trim(preg_replace('/\s+/', ' ', $texto));
    if($texto === ''){
        return null;
    }
    $clave = $tabla . '|' . mb_strtolower($texto);
    if(isset($cache[$clave])){
        return $cache[$clave];
    }
    [$id, $creado] = resolverCatalogoIdONuevo($conexion, $tabla, $colId, $colNombre, $texto);
    if($creado){
        registrarCatalogoPendiente($conexion, $tabla, $id, $texto, $etiqueta, '', 'peletizado');
    }
    return $cache[$clave] = $texto;
}

// Valida y resuelve el borrador completo para finalizar. Devuelve nombres (no ids).
function normalizarBorradorPeletizado($conexion, array $borrador){
    $mapas = mapasCatalogoPeletizado($conexion);
    $cacheLibres = [];

    $valorOp = fn($v) => (strncmp($v, 'x:', 2) === 0)
        ? resolverLibrePeletizado($conexion, 'operarios', 'id_operario', 'nombre_operario', 'nuevo operario', substr($v, 2), $cacheLibres)
        : ($mapas['operario'][$v] ?? null);

    $idOp1 = (string) ($borrador['id_operario'] ?? '');
    $operario1 = $idOp1 !== '' ? $valorOp($idOp1) : null;
    if($operario1 === null || $operario1 === ''){
        return ['filas' => [], 'error' => 'Elige el operario 1.'];
    }
    $idOp2 = (string) ($borrador['id_operario2'] ?? '');
    $operario2 = $idOp2 !== '' ? ($valorOp($idOp2) ?? '') : '';

    $filas = [];
    foreach($borrador['colores'] ?? [] as $c){
        $valorColor = (string) $c['color'];
        if($valorColor === ''){
            continue; // fila sin color elegido: se ignora
        }
        $color = (strncmp($valorColor, 'x:', 2) === 0)
            ? resolverLibrePeletizado($conexion, 'colores', 'id_color', 'nombre_color', 'nuevo color', substr($valorColor, 2), $cacheLibres)
            : ($mapas['color'][$valorColor] ?? null);
        if($color === null){
            return ['filas' => [], 'error' => 'Hay una fila con un color inválido.'];
        }

        $alta_retal = enteroPeletizado($c['alta_retal'] ?? '');
        $baja       = enteroPeletizado($c['baja'] ?? '');
        $refiltrado = enteroPeletizado($c['refiltrado'] ?? '');
        $soplado    = enteroPeletizado($c['soplado'] ?? '');
        $torta      = enteroPeletizado($c['torta'] ?? '');
        $limpieza   = enteroPeletizado($c['limpieza'] ?? '');
        if(in_array(null, [$alta_retal, $baja, $refiltrado, $soplado, $torta, $limpieza], true)){
            return ['filas' => [], 'error' => 'Hay un número inválido en la tabla.'];
        }

        $filas[] = [
            'color' => $color,
            'alta_retal' => $alta_retal, 'baja' => $baja, 'refiltrado' => $refiltrado,
            'soplado' => $soplado, 'torta' => $torta, 'limpieza' => $limpieza,
            'total' => $alta_retal + $baja + $refiltrado + $soplado + $torta + $limpieza,
        ];
    }

    return [
        'filas' => $filas,
        'operario1' => $operario1,
        'operario2' => $operario2,
        'observaciones' => (string) ($borrador['observaciones'] ?? ''),
        'error' => null,
    ];
}

// Filas para REGISTROS (B..O). Si no hay ninguna, se manda un único registro
// vacío (0 en todo) para dejar constancia del turno con la observación.
function filasRegistrosPeletizado($planilla, array $filas, $operario1, $operario2, $observaciones){
    $registros = [];
    foreach($filas as $f){
        $registros[] = [
            $planilla['fecha_planilla'], $planilla['nombre_maquina'], $planilla['nombre_turno'], $operario1, $operario2,
            $f['color'], $f['alta_retal'], $f['baja'], $f['refiltrado'], $f['soplado'], $f['torta'], $f['limpieza'], $f['total'],
            $observaciones,
        ];
    }
    if(empty($registros)){
        $registros[] = [
            $planilla['fecha_planilla'], $planilla['nombre_maquina'], $planilla['nombre_turno'], $operario1, $operario2,
            '', 0, 0, 0, 0, 0, 0, 0,
            $observaciones,
        ];
    }
    return $registros;
}
