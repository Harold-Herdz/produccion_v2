<?php
// Modelo del formulario de Plana

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

// Validar fecha Y-m-d
function validarFechaPlana($fecha){
    $fecha = trim((string) $fecha);
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if($d && $d->format('Y-m-d') === $fecha){
        return $fecha;
    }
    return null;
}

// Log del día
function obtenerLogPorIdDiaPlana($conexion, $id_dia){
    $stmt = $conexion->prepare("SELECT * FROM plana_sheet WHERE id_dia = ? LIMIT 1");
    $stmt->bind_param('s', $id_dia);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Día en proceso (fecha más reciente)
function obtenerDiaEnProcesoPlana($conexion){
    $res = $conexion->query("SELECT * FROM plana_sheet WHERE estado = 'en_proceso' ORDER BY fecha DESC, id_log DESC LIMIT 1");
    return $res ? $res->fetch_assoc() : null;
}

// Abrir o reabrir día
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

// Sumar 1 al contador
function incrementarContadorDiaPlana($conexion, $id_dia){
    $stmt = $conexion->prepare("UPDATE plana_sheet SET total_registros = total_registros + 1 WHERE id_dia = ?");
    $stmt->bind_param('s', $id_dia);
    $stmt->execute();
}

// Cerrar día con total y PDF
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
// Filas de REGISTROS del día
// null si el Sheet falla
// Reintenta si faltan filas
function filasDelDiaPlana($fechaObjetivo, $minimo = 0){
    $delDia = null;
    for($intento = 1; $intento <= 3; $intento++){
        $delDia = leerFilasDelDiaPlana($fechaObjetivo);
        if($delDia !== null && count($delDia) >= $minimo){
            return $delDia;
        }
        if($intento < 3){ sleep(2); }
    }
    return $delDia; // null si no se pudo leer
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

// ¿La fila ya se guardó?
function yaExisteRegistroPlana($fecha, $operario, $maquina, $referencia, $pesoRollo, $pesoRetal, $bultos, $pesoTotal){
    $filas = leerFilasDelDiaPlana($fecha);
    if($filas === null){
        return false;
    }
    $recientes = array_slice($filas, -5); // últimas 5 filas
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

// Paquete de cierre (PDF + LOGS)
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

// Reintenta cierres pendientes
// Cierra día y confirma con Google
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
// Máquinas y referencias: ver catalogosModel
// obtenerReferenciasEspOrdenadas() en shared/catalogosModel.php

/* =================================================
   NOMBRE ESCRITO A MANO ("Otro" de operario)
================================================= */
// Solo letras y espacios
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

// Nombre de catálogo por id
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
// Peso válido (vacío = 0)
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

// Bultos enteros (vacío = 0)
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
