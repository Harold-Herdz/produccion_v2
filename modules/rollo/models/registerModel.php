<?php
// Modelo de Register: formulario de registro de producción de Rollos

date_default_timezone_set('America/Bogota');

require_once __DIR__ . '/../spreadsheet/spreadsheetPdf.php';
require_once dirname(__DIR__, 3) . '/import/importModel.php';
require_once dirname(__DIR__, 2) . '/shared/catalogosModel.php';

/* =================================================
   TABLA DE LOGS POR DÍA (local; espejo del LOGS del Sheet)
================================================= */
// Crear la tabla de logs por día si aún no existe
function asegurarTablaLogsRollo($conexion){
    $conexion->query("
        CREATE TABLE IF NOT EXISTS rollo_logs (
            id_log          INT(11) NOT NULL AUTO_INCREMENT,
            id_dia          VARCHAR(20) NOT NULL,
            fecha           DATE NOT NULL,
            estado          ENUM('en_proceso','completado') NOT NULL DEFAULT 'en_proceso',
            inicio          DATETIME NOT NULL,
            fin             DATETIME DEFAULT NULL,
            total_registros INT(11) NOT NULL DEFAULT 0,
            ruta_pdf        VARCHAR(255) DEFAULT NULL,
            creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_log),
            UNIQUE KEY id_dia (id_dia),
            KEY idx_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

// Id del día: R{yyyyMMdd}
function construirIdDia($fecha){
    return 'R' . date('Ymd', strtotime($fecha));
}

// Validar una fecha 'Y-m-d'; devuelve la fecha normalizada o null
function validarFechaRollo($fecha){
    $fecha = trim((string) $fecha);
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if($d && $d->format('Y-m-d') === $fecha){
        return $fecha;
    }
    return null;
}

// Log de un día por su id
function obtenerLogPorIdDia($conexion, $id_dia){
    $stmt = $conexion->prepare("SELECT * FROM rollo_logs WHERE id_dia = ? LIMIT 1");
    $stmt->bind_param('s', $id_dia);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// El día actualmente en proceso (debería haber a lo sumo uno)
function obtenerDiaEnProceso($conexion){
    $res = $conexion->query("SELECT * FROM rollo_logs WHERE estado = 'en_proceso' ORDER BY id_log DESC LIMIT 1");
    return $res ? $res->fetch_assoc() : null;
}

// Abrir el día (o reabrirlo si ya estaba completado: Caso D)
function abrirDia($conexion, $id_dia, $fecha){
    $log = obtenerLogPorIdDia($conexion, $id_dia);
    if($log){
        if($log['estado'] === 'completado'){
            $stmt = $conexion->prepare("UPDATE rollo_logs SET estado='en_proceso', fin=NULL WHERE id_dia = ?");
            $stmt->bind_param('s', $id_dia);
            $stmt->execute();
        }
        return obtenerLogPorIdDia($conexion, $id_dia);
    }
    $stmt = $conexion->prepare("INSERT INTO rollo_logs (id_dia, fecha, estado, inicio) VALUES (?, ?, 'en_proceso', NOW())");
    $stmt->bind_param('ss', $id_dia, $fecha);
    $stmt->execute();
    return obtenerLogPorIdDia($conexion, $id_dia);
}

// Sumar 1 al contador local de registros del día
function incrementarContadorDia($conexion, $id_dia){
    $stmt = $conexion->prepare("UPDATE rollo_logs SET total_registros = total_registros + 1 WHERE id_dia = ?");
    $stmt->bind_param('s', $id_dia);
    $stmt->execute();
}

// Cerrar el día: total real (contado del Sheet) y ruta del PDF
function cerrarDia($conexion, $id_dia, $total, $rutaPdf){
    $stmt = $conexion->prepare("
        UPDATE rollo_logs
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
function filasDelDiaRollo($fechaObjetivo){
    [$filas] = leerSheet(ROLLO_REGISTROS_CSV_URL, 'todo', null);
    $delDia = [];
    foreach($filas as $data){
        if(convertirFecha($data[1] ?? '') === $fechaObjetivo){
            $delDia[] = [
                'operario'    => limpiarNombre($data[2] ?? ''),
                'maquina'     => limpiarNombre($data[3] ?? ''),
                'referencia'  => limpiarNombre($data[4] ?? ''),
                'color'       => limpiarNombre($data[5] ?? ''),
                'peso_rollo'  => $data[6] ?? '',
                'peso_retal'  => $data[7] ?? '',
                'peso_total'  => $data[8] ?? '',
            ];
        }
    }
    return $delDia;
}

// Arma el paquete de cierre (PDF + fila de LOGS) para un día ya presente en el Sheet
function prepararCierreRollo($logDia){
    $filas = filasDelDiaRollo($logDia['fecha']);
    $pdfBytes = generarPdfDiaRollo($logDia['fecha'], $filas);
    $total = count($filas);
    return [
        'id_dia' => $logDia['id_dia'],
        'total'  => $total,
        'log'    => [$logDia['id_dia'], $logDia['fecha'], $logDia['inicio'], date('Y-m-d H:i:s'), $total, 'COMPLETADO'],
        'pdf'    => [
            'nombre' => nombrePdfDiaRollo($logDia['id_dia']),
            'mes'    => date('m-Y', strtotime($logDia['fecha'])),
            'base64' => base64_encode($pdfBytes),
        ],
    ];
}

/* =================================================
   CATÁLOGOS DEL FORMULARIO
================================================= */
function obtenerOperariosActivosRollo($conexion){
    return $conexion->query("SELECT id_operario, nombre_operario FROM operarios WHERE estado = 1 ORDER BY nombre_operario");
}
function obtenerMaquinasActivasRollo($conexion){
    return $conexion->query("SELECT id_maquina, nombre_maquina FROM maquinas WHERE estado = 1 ORDER BY id_maquina");
}
// Referencias y colores: ver obtenerReferenciasOrdenadas()/obtenerColoresOrdenados() en shared/catalogosModel.php

// Nombre de un catálogo por id; null si no existe
function nombreCatalogo($conexion, $tabla, $columnaId, $columnaNombre, $id){
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
function pesoRollo($valor){
    $valor = trim((string) $valor);
    if($valor === ''){
        return 0.0;
    }
    if(!is_numeric($valor) || (float) $valor < 0){
        return null;
    }
    return (float) $valor;
}
