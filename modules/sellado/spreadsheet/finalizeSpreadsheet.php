<?php
/** @var mysqli $conexion */

// Finaliza el turno: envía REGISTROS + LOGS + PDF al Apps Script y limpia el borrador (AJAX, JSON)
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';
require_once __DIR__ . '/spreadsheetPdf.php';
require_once __DIR__ . '/appscript.php';

header('Content-Type: application/json');

$entrada  = json_decode(file_get_contents('php://input'), true) ?: [];
$codigo   = $entrada['codigo'] ?? '';
$maquinas = $entrada['maquinas'] ?? [];
// La nota va al PDF, no a la base de datos
$nota     = trim((string) ($entrada['nota'] ?? ''));

// Candado anti doble finalización
$lockName = 'sellado_planilla_' . preg_replace('/[^A-Za-z0-9_]/', '', $codigo);
$conexion->query("SELECT GET_LOCK('{$lockName}', 15)");

try {
    $planilla = obtenerPlanillaPorCodigo($conexion, $codigo);

    // Idempotente: ya finalizada
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

    if(!appScriptConfigurado()){
        echo json_encode(['ok' => false, 'error' => 'Falta configurar el Web App de Apps Script (modules/sellado/spreadsheet/appscript.php).']);
        return;
    }

    // Fecha cambiada: recodificar antes de cerrar
    $fecha = validarFechaPlanilla($entrada['fecha'] ?? '');
    if($fecha){
        [$planilla, $errFecha] = recodificarPlanilla($conexion, $planilla, $fecha);
        if($errFecha){
            echo json_encode(['ok' => false, 'error' => $errFecha]);
            return;
        }
    }
    $codigo = $planilla['codigo'];

    $resultado = guardarPlanilla($conexion, $planilla, $maquinas);

    $total = contarRegistrosPlanilla($conexion, $codigo);
    if($total === 0){
        echo json_encode(['ok' => false, 'error' => 'No hay registros para finalizar el turno.']);
        return;
    }

    $filas = construirFilasRegistros($conexion, $planilla, $maquinas);
    $log   = construirLogPlanilla($planilla, count($filas));
    $pdfBytes = generarPdfPlanilla($conexion, $planilla, $nota);

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

    $yaExportado = (!$respuesta['ok'] && ($respuesta['error'] ?? '') === 'yaExportado');

    if(!$respuesta['ok'] && !$yaExportado){
        // Envío falló: el borrador queda intacto para reintentar
        echo json_encode(['ok' => false, 'error' => $respuesta['error'] ?? 'No se pudo enviar el turno a Google.']);
        return;
    }

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
