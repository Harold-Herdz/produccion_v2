<?php
// Modelo de la planilla de Extrusión

date_default_timezone_set('America/Bogota');

require_once dirname(__DIR__, 2) . '/shared/catalogosModel.php';

/* =================================================
   TURNOS Y CÓDIGOS
================================================= */
// Turnos y códigos
function turnosExtrusion(){
    return ['Día' => '01', 'Noche' => '02', '18 Horas' => '03'];
}

// Código del turno: E{yyyyMMdd}_T{01|02|03}
function construirCodigoExtrusion($fecha, $nombreTurno){
    $cod = turnosExtrusion()[$nombreTurno] ?? '00';
    return 'E' . date('Ymd', strtotime($fecha)) . '_T' . $cod;
}

// Id del día en LOGS
function idDiaExtrusion($fecha){
    return 'E' . date('Ymd', strtotime($fecha));
}

// Nombre del PDF: E{yyyyMMdd}_E{máquina}
function nombrePdfExtrusion($fecha, $nombreMaquina){
    $num = (int) preg_replace('/\D/', '', $nombreMaquina);
    return 'E' . date('Ymd', strtotime($fecha)) . '_E' . str_pad($num, 2, '0', STR_PAD_LEFT) . '.pdf';
}

// Validar fecha Y-m-d
function validarFechaExtrusion($fecha){
    $fecha = trim((string) $fecha);
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return ($d && $d->format('Y-m-d') === $fecha) ? $fecha : null;
}

/* =================================================
   CATÁLOGOS DEL FORMULARIO
================================================= */
// Máquinas del área extrusión
function maquinasExtrusion($conexion){
    return obtenerMaquinasConReferencias($conexion, 'extrusion')['maquinas'];
}

// Turnos permitidos
function turnosCatalogoExtrusion($conexion){
    $res = $conexion->query("
        SELECT id_turno, nombre_turno FROM turnos
        WHERE estado = 1 AND nombre_turno IN ('Día', 'Noche', '18 Horas')
        ORDER BY FIELD(nombre_turno, 'Día', 'Noche', '18 Horas')
    ");
    return $res->fetch_all(MYSQLI_ASSOC);
}

function operadoresExtrusion($conexion){
    return $conexion->query("SELECT id_operador, nombre_operador FROM operadores WHERE estado = 1 ORDER BY nombre_operador")->fetch_all(MYSQLI_ASSOC);
}

// Referencias normales (orden numérico)
function referenciasExtrusion($conexion){
    return $conexion->query("
        SELECT id_referencia AS id, nombre_referencia AS nombre FROM referencias
        WHERE estado = 1 AND nombre_referencia <> ''
        ORDER BY CAST(REPLACE(REGEXP_SUBSTR(nombre_referencia, '[0-9]+(,[0-9]+)?'), ',', '.') AS DECIMAL(12,2)), nombre_referencia
    ")->fetch_all(MYSQLI_ASSOC);
}

function laminasExtrusion($conexion){
    return $conexion->query("SELECT id_lamina_p AS id, nombre_lamina_p AS nombre FROM lamina_p WHERE estado = 1 AND nombre_lamina_p <> '' ORDER BY nombre_lamina_p")->fetch_all(MYSQLI_ASSOC);
}

// Mapas id => nombre
function mapasCatalogoExtrusion($conexion){
    $mapa = function(array $filas, $id, $nombre){
        $m = [];
        foreach($filas as $f){ $m[(string) $f[$id]] = $f[$nombre]; }
        return $m;
    };
    return [
        'ref'    => $mapa(referenciasExtrusion($conexion), 'id', 'nombre'),
        'esp'    => $mapa(obtenerReferenciasEspOrdenadas($conexion), 'id', 'nombre'),
        'color'  => $mapa(obtenerColoresOrdenados($conexion), 'id_color', 'nombre_color'),
        'lamina' => $mapa(laminasExtrusion($conexion), 'id', 'nombre'),
    ];
}

/* =================================================
   CONSULTAS DE PLANILLAS
================================================= */
// Consulta base con nombres
function sqlPlanillaExtrusion(){
    return "SELECT p.*, m.nombre_maquina, t.nombre_turno, o.nombre_operador
            FROM extrusion_sheet p
            JOIN maquinas m ON m.id_maquina = p.id_maquina
            JOIN turnos t ON t.id_turno = p.id_turno
            JOIN operadores o ON o.id_operador = p.id_operador";
}

function obtenerPlanillaExtrusion($conexion, $id){
    $stmt = $conexion->prepare(sqlPlanillaExtrusion() . " WHERE p.id_planilla = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function buscarPlanillaExtrusion($conexion, $codigo, $idMaquina){
    $stmt = $conexion->prepare(sqlPlanillaExtrusion() . " WHERE p.codigo = ? AND p.id_maquina = ? LIMIT 1");
    $stmt->bind_param('si', $codigo, $idMaquina);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Planillas abiertas (para continuar)
function planillasAbiertasExtrusion($conexion){
    return $conexion->query(sqlPlanillaExtrusion() . " WHERE p.estado = 'abierta' ORDER BY p.creado_en DESC")->fetch_all(MYSQLI_ASSOC);
}

// Finalizadas por máquina y fecha
function planillasFinalizadasExtrusion($conexion, $idMaquina, $fecha){
    $stmt = $conexion->prepare(sqlPlanillaExtrusion() . " WHERE p.id_maquina = ? AND p.fecha_planilla = ? AND p.estado = 'finalizada'
        ORDER BY FIELD(t.nombre_turno, 'Día', 'Noche', '18 Horas')");
    $stmt->bind_param('is', $idMaquina, $fecha);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function crearPlanillaExtrusion($conexion, $fecha, $idTurno, $nombreTurno, $idMaquina, $idOperador){
    $codigo = construirCodigoExtrusion($fecha, $nombreTurno);
    $stmt = $conexion->prepare("INSERT INTO extrusion_sheet (codigo, fecha_planilla, id_maquina, id_turno, id_operador) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiii', $codigo, $fecha, $idMaquina, $idTurno, $idOperador);
    $stmt->execute();
    return obtenerPlanillaExtrusion($conexion, $conexion->insert_id);
}

function cancelarPlanillaExtrusion($conexion, $id){
    $stmt = $conexion->prepare("DELETE FROM extrusion_sheet WHERE id_planilla = ? AND estado = 'abierta'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
}

// Guardar borrador (JSON de segmentos)
function guardarBorradorExtrusion($conexion, $id, $json){
    $stmt = $conexion->prepare("UPDATE extrusion_sheet SET filas = ? WHERE id_planilla = ? AND estado = 'abierta'");
    $stmt->bind_param('si', $json, $id);
    $stmt->execute();
}

// Cerrar planilla con detalle
function cerrarPlanillaExtrusion($conexion, $id, $total, $rutaPdf, $jsonFinal){
    $stmt = $conexion->prepare("UPDATE extrusion_sheet SET estado = 'finalizada', total_registros = ?, ruta_pdf = ?, filas = ?, finalizado_en = NOW() WHERE id_planilla = ?");
    $stmt->bind_param('issi', $total, $rutaPdf, $jsonFinal, $id);
    $stmt->execute();
}

/* =================================================
   SEGMENTOS (cambios de referencia / color / lámina)
================================================= */
// Limpiar borrador del navegador
function limpiarSegmentosExtrusion($entrada){
    $out = [];
    foreach((array) $entrada as $seg){
        if(!is_array($seg)){ continue; }
        $pesos = [];
        foreach((array) ($seg['pesos'] ?? []) as $p){
            $pesos[] = substr(trim((string) $p), 0, 12);
        }
        $out[] = [
            'cambio' => in_array($seg['cambio'] ?? null, ['referencia', 'color', 'lamina'], true) ? $seg['cambio'] : null,
            'ref'    => mb_substr((string) ($seg['ref'] ?? ''), 0, 70),
            'color'  => mb_substr((string) ($seg['color'] ?? ''), 0, 70),
            'lamina' => mb_substr((string) ($seg['lamina'] ?? ''), 0, 70),
            'pesos'  => array_slice($pesos, 0, 10),
        ];
    }
    return array_slice($out, 0, 60);
}

// Nombre de un valor "Otro" (x:texto): usa el existente o lo crea (solo al finalizar)
function resolverLibreExtrusion($conexion, $tipo, $texto, array &$cache){
    $texto = trim(preg_replace('/\s+/', ' ', $texto));
    if($texto === ''){
        return null;
    }
    $clave = $tipo . '|' . mb_strtolower($texto);
    if(isset($cache[$clave])){
        return $cache[$clave];
    }
    if($tipo === 'ref'){
        // Si ya existe (normal o especial) se usa; si no, "… ESP" va a especiales
        foreach([['referencias', 'nombre_referencia'], ['referencias_esp', 'nombre_referencia_esp']] as [$tabla, $col]){
            $stmt = $conexion->prepare("SELECT {$col} AS n FROM {$tabla} WHERE {$col} = ? LIMIT 1");
            $stmt->bind_param('s', $texto);
            $stmt->execute();
            $fila = $stmt->get_result()->fetch_assoc();
            if($fila){ return $cache[$clave] = $fila['n']; }
        }
        $esEsp = (bool) preg_match('/\bESP$/i', $texto);
        $def = $esEsp ? ['referencias_esp', 'id_referencia_esp', 'nombre_referencia_esp'] : ['referencias', 'id_referencia', 'nombre_referencia'];
        $etiqueta = 'nueva referencia';
    } elseif($tipo === 'color'){
        $def = ['colores', 'id_color', 'nombre_color'];
        $etiqueta = 'nuevo color';
    } else {
        $def = ['lamina_p', 'id_lamina_p', 'nombre_lamina_p'];
        $etiqueta = 'nueva lámina P';
    }
    [$id, $creado] = resolverCatalogoIdONuevo($conexion, $def[0], $def[1], $def[2], $texto);
    if($creado){
        registrarCatalogoPendiente($conexion, $def[0], $id, $texto, $etiqueta, '', 'extrusion');
    }
    return $cache[$clave] = $texto;
}

// Ids a nombres (rollos)
function normalizarRollosExtrusion($conexion, array $segmentos){
    $mapas = mapasCatalogoExtrusion($conexion);
    $cacheLibres = [];
    $rollos = [];
    $n = 0;
    foreach($segmentos as $seg){
        $ref = null;
        $valor = (string) $seg['ref'];
        if(strncmp($valor, 'r:', 2) === 0){ $ref = $mapas['ref'][substr($valor, 2)] ?? null; }
        elseif(strncmp($valor, 'e:', 2) === 0){ $ref = $mapas['esp'][substr($valor, 2)] ?? null; }
        elseif(strncmp($valor, 'x:', 2) === 0){ $ref = resolverLibreExtrusion($conexion, 'ref', substr($valor, 2), $cacheLibres); }
        $valorColor = (string) $seg['color'];
        $color = (strncmp($valorColor, 'x:', 2) === 0)
            ? resolverLibreExtrusion($conexion, 'color', substr($valorColor, 2), $cacheLibres)
            : ($mapas['color'][$valorColor] ?? null);
        $valorLamina = (string) $seg['lamina'];
        $lamina = (strncmp($valorLamina, 'x:', 2) === 0)
            ? resolverLibreExtrusion($conexion, 'lamina', substr($valorLamina, 2), $cacheLibres)
            : ($mapas['lamina'][$valorLamina] ?? null);

        foreach($seg['pesos'] as $p){
            if($p === ''){ continue; }
            if(!is_numeric($p) || (float) $p < 0){
                return ['rollos' => [], 'error' => 'Hay un peso inválido.'];
            }
            if((float) $p == 0){ continue; }
            if($ref === null || $color === null){
                return ['rollos' => [], 'error' => 'Elige referencia y color antes de pesar.'];
            }
            $n++;
            $rollos[] = ['n' => $n, 'referencia' => $ref, 'color' => $color, 'lamina' => $lamina ?? '', 'peso' => round((float) $p, 2)];
        }
    }
    return ['rollos' => $rollos, 'error' => null];
}

// Filas para REGISTROS (B..I)
function filasRegistrosExtrusion($planilla, array $rollos){
    $filas = [];
    foreach($rollos as $r){
        $filas[] = [
            $planilla['fecha_planilla'], $planilla['nombre_maquina'], $planilla['nombre_turno'], $planilla['nombre_operador'],
            $r['referencia'], $r['color'], $r['peso'], $r['lamina'],
        ];
    }
    return $filas;
}
