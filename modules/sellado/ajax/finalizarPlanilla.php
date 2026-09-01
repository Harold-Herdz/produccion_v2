<?php
/** @var mysqli $conexion */

// Finalización del turno: guarda pendientes, cierra la planilla y genera el PDF (AJAX, JSON)

// Importar authMiddleware.php (valida sesión)
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar registerModel.php y el generador de PDF
require_once dirname(__DIR__) . '/models/registerModel.php';
require_once dirname(__DIR__) . '/lib/pdfPlanilla.php';

header('Content-Type: application/json');

$entrada  = json_decode(file_get_contents('php://input'), true) ?: [];
$codigo   = $entrada['codigo'] ?? '';
$maquinas = $entrada['maquinas'] ?? [];

// Candado para que dos finalizaciones simultáneas no se pisen
$lockName = 'sellado_planilla_' . preg_replace('/[^A-Za-z0-9_]/', '', $codigo);
$conexion->query("SELECT GET_LOCK('{$lockName}', 10)");

try {
    $planilla = obtenerPlanillaPorCodigo($conexion, $codigo);

    // Ya finalizada: devolver el PDF existente (idempotente)
    if($planilla && $planilla['estado'] === 'finalizada'){
        echo json_encode([
            'ok'       => true,
            'yaHecho'  => true,
            'mensaje'  => 'Este turno ya estaba finalizado.',
            'pdf_url'  => $planilla['ruta_pdf'] ? urlPdfPlanilla($planilla['ruta_pdf']) : null,
        ]);
        return;
    }

    if(!$planilla || $planilla['estado'] !== 'abierta'){
        echo json_encode(['ok' => false, 'error' => 'La planilla no está disponible.']);
        return;
    }

    // 1-2. Validar y guardar los cambios pendientes
    $resultado = guardarPlanilla($conexion, $planilla, $maquinas);

    // 3. Verificar que haya al menos un registro
    $total = contarRegistrosPlanilla($conexion, $codigo);
    if($total === 0){
        echo json_encode(['ok' => false, 'error' => 'No hay registros para finalizar el turno.']);
        return;
    }

    // 4. Cerrar el turno
    if(!finalizarPlanillaSobre($conexion, $codigo, $total)){
        echo json_encode(['ok' => false, 'error' => 'No se pudo finalizar (¿ya estaba cerrada?).']);
        return;
    }

    // 5-6. Generar y guardar el PDF
    $rutaPdf = generarPdfPlanilla($conexion, $planilla);
    guardarRutaPdfPlanilla($conexion, $codigo, $rutaPdf);

    // 7-8. Confirmar; la nueva planilla se prepara al recargar
    echo json_encode([
        'ok'        => true,
        'total'     => $total,
        'avisos'    => $resultado['avisos'],
        'pdf_url'   => urlPdfPlanilla($rutaPdf),
    ]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Ocurrió un error al finalizar el turno. Intenta de nuevo.']);
} finally {
    $conexion->query("SELECT RELEASE_LOCK('{$lockName}')");
}
