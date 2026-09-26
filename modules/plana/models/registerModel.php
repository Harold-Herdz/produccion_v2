<?php
// Modelo de Register: formulario de registro de pesos de Máquina Plana

date_default_timezone_set('America/Bogota');

require_once __DIR__ . '/../spreadsheet/pdfSpreadsheet.php';
require_once dirname(__DIR__, 3) . '/import/importModel.php';
require_once dirname(__DIR__, 2) . '/shared/catalogosModel.php';

/* =================================================
   TABLA DE LOGS POR DÍA (local; espejo del LOGS del Sheet)
   Tabla ya definida en bd_produccion_v2.sql
================================================= */
// Id del día: MP{yyyyMMdd}
function construirIdDiaPlana($fecha){
    return 'MP' . date('Ymd', strtotime($fecha));
}

// Validar una fecha 'Y-m-d'; devuelve la fecha normalizada o null
function validarFechaPlana($fecha){
    $fecha = trim((string) $fecha);
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if($d && $d->format('Y-m-d') === $fecha){
        return $fecha;
    }
    return null;
}

// Log de un día por su id
function obtenerLogPorIdDiaPlana($conexion, $id_dia){
    $stmt = $conexion->prepare("SELECT * FROM plana_sheet WHERE id_dia = ? LIMIT 1");
    $stmt->bind_param('s', $id_dia);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// El día actualmente en proceso: el de fecha más reciente (por fecha, no por orden de
// creación: un día pasado que se abre después no debe pasar por "el actual")
function obtenerDiaEnProcesoPlana($conexion){
    $res = $conexion->query("SELECT * FROM plana_sheet WHERE estado = 'en_proceso' ORDER BY fecha DESC, id_log DESC LIMIT 1");
    return $res ? $res->fetch_assoc() : null;
}

// Abrir el día (o reabrirlo si ya estaba completado: Caso D)
function abrirDiaPlana($conexion, $id_dia, $fecha){
    $log = obtenerLogPorIdDiaPlana($conexion, $id_dia);
    if($log){
        if($log['estado'] === 'completado'){
            $stmt = $conexion->prepare("UPDATE plana_sheet SET estado='en_proceso', fin=NULL WHERE id_dia = ?");
            $stmt->bind_param('s', $id_dia);
            $stmt->execute();
        }
        return obtenerLogPorIdDiaPlana($conexion, $id_dia);
    }
    $stmt = $conexion->prepare("INSERT INTO plana_sheet (id_dia, fecha, estado, inicio) VALUES (?, ?, 'en_proceso', NOW())");
    $stmt->bind_param('ss', $id_dia, $fecha);
    $stmt->execute();
    return obtenerLogPorIdDiaPlana($conexion, $id_dia);
}

// Sumar 1 al contador local de registros del día
function incrementarContadorDiaPlana($conexion, $id_dia){
    $stmt = $conexion->prepare("UPDATE plana_sheet SET total_registros = total_registros + 1 WHERE id_dia = ?");
    $stmt->bind_param('s', $id_dia);
    $stmt->execute();
}

// Cerrar el día: total real (contado del Sheet) y ruta del PDF
function cerrarDiaPlana($conexion, $id_dia, $total, $rutaPdf){
    $stmt = $conexion->prepare("
        UPDATE plana_sheet
        SET estado = 'completado', fin = NOW(), total_registros = ?, ruta_pdf = ?
        WHERE id_dia = ?
    ");
    $stmt->bind_param('iss', $total, $rutaPdf, $id_dia);
    $stmt->execute();
}

/* =================================================
   CIERRE DE DÍA (lee REGISTROS del Sheet y arma PDF + fila de LOGS)
================================================= */
// Filas de REGISTROS que corresponden a una fecha dada
// Devuelve null si el Sheet no se pudo leer (nunca un arreglo vacío por error: un PDF
// "Sin registros" subido por una lectura fallida pisaría el PDF bueno del día).
// $minimo: cuántas filas se esperan como mínimo (el contador local del día); si el CSV
// de Google aún no muestra un registro recién escrito, se reintenta unos segundos.
function filasDelDiaPlana($fechaObjetivo, $minimo = 0){
    $delDia = null;
    for($intento = 1; $intento <= 3; $intento++){
        $delDia = leerFilasDelDiaPlana($fechaObjetivo);
        if($delDia !== null && count($delDia) >= $minimo){
            return $delDia;
        }
        if($intento < 3){ sleep(2); }
    }
    return $delDia; // null si nunca se pudo leer; si se leyó pero faltan filas, lo que haya
}

function leerFilasDelDiaPlana($fechaObjetivo){
    [$filas] = leerSheet(PLANA_REGISTROS_CSV_URL, 'todo', null);
    if($filas === null){
        return null;
    }
    $delDia = [];
    foreach($filas as $data){
        if(convertirFecha($data[1] ?? '') === $fechaObjetivo){
            $delDia[] = [
                'operario'    => limpiarNombre($data[2] ?? ''),
                'maquina'     => limpiarNombre($data[3] ?? ''),
                'referencia'  => limpiarNombre($data[4] ?? ''),
                'peso_rollo'  => $data[5] ?? '',
                'peso_retal'  => $data[6] ?? '',
                'bultos'      => $data[7] ?? '',
                'peso_total'  => $data[8] ?? '',
            ];
        }
    }
    return $delDia;
}

// Verifica si la fila ya se guardó (evita falso error)
function yaExisteRegistroPlana($fecha, $operario, $maquina, $referencia, $pesoRollo, $pesoRetal, $bultos, $pesoTotal){
    $filas = leerFilasDelDiaPlana($fecha);
    if($filas === null){
        return false;
    }
    $recientes = array_slice($filas, -5); // solo las últimas 5 filas del día
    foreach($recientes as $fila){
        if($fila['operario'] === $operario
            && $fila['maquina'] === $maquina
            && $fila['referencia'] === $referencia
            && abs(convertirNumero($fila['peso_rollo']) - $pesoRollo) < 0.01
            && abs(convertirNumero($fila['peso_retal']) - $pesoRetal) < 0.01
            && (int) $fila['bultos'] === (int) $bultos
            && abs(convertirNumero($fila['peso_total']) - $pesoTotal) < 0.01
        ){
            return true;
        }
    }
    return false;
}

// Arma el paquete de cierre (PDF + fila de LOGS) para un día ya presente en el Sheet
// Devuelve null si no se pudo leer el Sheet (no se debe cerrar con datos vacíos).
function prepararCierrePlana($logDia){
    $filas = filasDelDiaPlana($logDia['fecha'], (int) ($logDia['total_registros'] ?? 0));
    if($filas === null){
        return null;
    }
    $pdfBytes = generarPdfDiaPlana($logDia['fecha'], $filas);
    $total = count($filas);
    return [
        'id_dia' => $logDia['id_dia'],
        'total'  => $total,
        'log'    => [$logDia['id_dia'], $logDia['fecha'], $logDia['inicio'], date('Y-m-d H:i:s'), $total, 'COMPLETADO'],
        'pdf'    => [
            'nombre' => nombrePdfDiaPlana($logDia['id_dia']),
            'mes'    => date('m-Y', strtotime($logDia['fecha'])),
            'base64' => base64_encode($pdfBytes),
        ],
    ];
}

// Reintenta el cierre (PDF + fila de LOGS) de días que quedaron cerrados en local
// sin que Google lo confirmara (cierre perdido por un error/timeout del Apps Script),
// o que quedaron "en proceso" aunque ya hay un día más nuevo. El cierre es un upsert
// en el Apps Script, así que repetirlo no duplica nada.
// Cierra un día: genera el PDF desde el Sheet, lo manda junto con su fila de LOGS
// (upsert por id_dia) y SOLO si Google confirma con la URL del PDF lo marca cerrado en
// local. Si algo falla queda pendiente y reintentarCierresPendientesPlana() lo retoma.
function cerrarDiaConfirmadoPlana($conexion, $dia){
    $cierre = prepararCierrePlana($dia);
    if($cierre === null){
        return false;
    }
    for($intento = 1; $intento <= 2; $intento++){
        $resp = enviarAppScriptPlana(['cierre' => $cierre]);
        if(!empty($resp['ok']) && !empty($resp['cierre_pdf_url'])){
            cerrarDiaPlana($conexion, $dia['id_dia'], $cierre['total'], $resp['cierre_pdf_url']);
            return true;
        }
    }
    return false;
}

function reintentarCierresPendientesPlana($conexion, $limite = 2){
    $res = $conexion->query("
        SELECT * FROM plana_sheet
        WHERE (estado = 'completado' AND (ruta_pdf IS NULL OR ruta_pdf = ''))
           OR (estado = 'en_proceso' AND fecha < (SELECT MAX(fecha) FROM plana_sheet WHERE estado = 'en_proceso'))
        ORDER BY fecha
        LIMIT " . (int) $limite
    );
    $pendientes = [];
    while($res && ($dia = $res->fetch_assoc())){ $pendientes[] = $dia; }

    $reintentados = 0;
    foreach($pendientes as $dia){
        if(cerrarDiaConfirmadoPlana($conexion, $dia)){ $reintentados++; }
    }
    return $reintentados;
}

/* =================================================
   CATÁLOGOS DEL FORMULARIO
================================================= */
function obtenerOperariosActivosPlana($conexion){
    return $conexion->query("SELECT id_operario, nombre_operario FROM operarios WHERE estado = 1 ORDER BY nombre_operario");
}
// Máquinas (área 'plana') y referencias especiales: ver obtenerMaquinasConReferencias()/
// obtenerReferenciasEspOrdenadas() en shared/catalogosModel.php

/* =================================================
   NOMBRE ESCRITO A MANO ("Otro" de operario)
================================================= */
// Solo letras y espacios (incluye acentos/ñ); rechaza números y símbolos
function nombrePropioValidoPlana($texto){
    return (bool) preg_match('/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+$/u', $texto);
}
// Cada palabra con mayúscula inicial
function capitalizarNombrePlana($texto){
    $limpio = trim(preg_replace('/\s+/', ' ', (string) $texto));
    if($limpio === ''){
        return '';
    }
    $palabras = explode(' ', mb_strtolower($limpio, 'UTF-8'));
    foreach($palabras as &$palabra){
        $palabra = mb_strtoupper(mb_substr($palabra, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($palabra, 1, null, 'UTF-8');
    }
    return implode(' ', $palabras);
}

// Nombre de un catálogo por id; null si no existe
function nombreCatalogoPlana($conexion, $tabla, $columnaId, $columnaNombre, $id){
    $stmt = $conexion->prepare("SELECT {$columnaNombre} AS n FROM {$tabla} WHERE {$columnaId} = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    return $fila['n'] ?? null;
}

/* =================================================
   VALIDACIÓN DE PESOS
================================================= */
// Vacío -> 0; rechaza negativos (devuelve null si es inválido)
function pesoPlana($valor){
    $valor = trim((string) $valor);
    if($valor === ''){
        return 0.0;
    }
    if(!is_numeric($valor) || (float) $valor < 0){
        return null;
    }
    return (float) $valor;
}

// Bultos: entero cerrado >= 0 (vacío -> 0); null si es inválido
function bultosPlana($valor){
    $valor = trim((string) $valor);
    if($valor === ''){
        return 0;
    }
    if(!ctype_digit($valor)){
        return null;
    }
    return (int) $valor;
}
