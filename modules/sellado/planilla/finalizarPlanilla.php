<?php
/** @var mysqli $conexion */

// Finalización del turno: guarda pendientes, envía las filas a la hoja REGISTROS + LOGS
// (vía Apps Script Web App), guarda el PDF en Drive, cierra la planilla y borra el borrador.
// Los registros aparecen en el Historial solo tras "Importar". (AJAX, JSON)

// Importar authMiddleware.php (valida sesión)
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar registerModel.php, el generador de PDF y la config del Apps Script
require_once dirname(__DIR__) . '/models/registerModel.php';
require_once __DIR__ . '/pdfPlanilla.php';
require_once __DIR__ . '/appscript.php';

header('Content-Type: application/json');

$entrada  = json_decode(file_get_contents('php://input'), true) ?: [];
$codigo   = $entrada['codigo'] ?? '';
$maquinas = $entrada['maquinas'] ?? [];
// Nota general del turno: va al PDF pero NO se guarda en la base de datos
$nota     = trim((string) ($entrada['nota'] ?? ''));

// Candado para que dos finalizaciones simultáneas no se pisen
$lockName = 'sellado_planilla_' . preg_replace('/[^A-Za-z0-9_]/', '', $codigo);
$conexion->query("SELECT GET_LOCK('{$lockName}', 15)");

try {
    $planilla = obtenerPlanillaPorCodigo($conexion, $codigo);

    // Ya finalizada: devolver el PDF de Drive existente (idempotente)
    if($planilla && $planilla['estado'] === 'finalizada'){
        echo json_encode([
            'ok'      => true,
            'yaHecho' => true,
            'mensaje' => 'Este turno ya estaba finalizado.',
            'pdf_url' => $planilla['ruta_pdf'] ?: null,
        ]);
        return;
    }

    if(!$planilla || $planilla['estado'] !== 'abierta'){
        echo json_encode(['ok' => false, 'error' => 'La planilla no está disponible.']);
        return;
    }

    // El Web App debe estar configurado
    if(!appScriptConfigurado()){
        echo json_encode(['ok' => false, 'error' => 'Falta configurar el Web App de Apps Script (modules/sellado/planilla/appscript.php).']);
        return;
    }

    // Si el supervisor cambió la fecha, recodificar antes de cerrar
    $fecha = validarFechaPlanilla($entrada['fecha'] ?? '');
    if($fecha){
        [$planilla, $errFecha] = recodificarPlanilla($conexion, $planilla, $fecha);
        if($errFecha){
            echo json_encode(['ok' => false, 'error' => $errFecha]);
            return;
        }
    }
    $codigo = $planilla['codigo'];

    // 1. Guardar los cambios pendientes (borrador)
    $resultado = guardarPlanilla($conexion, $planilla, $maquinas);

    // 2. Verificar que haya al menos un registro
    $total = contarRegistrosPlanilla($conexion, $codigo);
    if($total === 0){
        echo json_encode(['ok' => false, 'error' => 'No hay registros para finalizar el turno.']);
        return;
    }

    // 3. Armar las filas de REGISTROS + la fila de LOGS
    $filas = construirFilasRegistros($conexion, $planilla, $maquinas);
    $log   = construirLogPlanilla($planilla, count($filas));

    // 4. Generar el PDF (incluye la nota general)
    $pdfBytes = generarPdfPlanilla($conexion, $planilla, $nota);

    // 5. Enviar todo al Apps Script (REGISTROS + LOGS + PDF a Drive)
    $respuesta = enviarAppScript([
        'codigo'    => $codigo,
        'registros' => $filas,
        'log'       => $log,
        'pdf'       => [
            'nombre' => nombrePdfPlanilla($codigo),
            'mes'    => date('m-Y', strtotime($planilla['fecha_planilla'])),
            'dia'    => date('d-m-Y', strtotime($planilla['fecha_planilla'])),
            'base64' => base64_encode($pdfBytes),
        ],
    ]);

    // 6. Interpretar la respuesta
    $yaExportado = (!$respuesta['ok'] && ($respuesta['error'] ?? '') === 'yaExportado');

    if(!$respuesta['ok'] && !$yaExportado){
        // No se pudo enviar: el borrador queda intacto para reintentar
        echo json_encode(['ok' => false, 'error' => $respuesta['error'] ?? 'No se pudo enviar el turno a Google.']);
        return;
    }

    // 7. Cerrar el turno y borrar el borrador local (los registros vuelven al importar)
    finalizarPlanillaSobre($conexion, $codigo, $total);
    guardarRutaPdfPlanilla($conexion, $codigo, $respuesta['pdf_url'] ?? '');
    borrarFilasPlanilla($conexion, $planilla);

    echo json_encode([
        'ok'      => true,
        'yaHecho' => $yaExportado,
        'total'   => $total,
        'avisos'  => $resultado['avisos'],
        'pdf_url' => $respuesta['pdf_url'] ?? null,
    ]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Ocurrió un error al finalizar el turno. Intenta de nuevo.']);
} finally {
    $conexion->query("SELECT RELEASE_LOCK('{$lockName}')");
}
