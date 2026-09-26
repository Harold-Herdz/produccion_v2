<?php
/** @var mysqli $conexion */

// Finaliza turno (AJAX)
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';
require_once __DIR__ . '/pdfSpreadsheet.php';
require_once __DIR__ . '/appsScript.php';

header('Content-Type: application/json');
set_time_limit(120);

$entrada = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int) ($entrada['id'] ?? 0);

// Candado anti doble finalización
$lockName = 'extrusion_planilla_' . $id;
$conexion->query("SELECT GET_LOCK('{$lockName}', 15)");

try {
    $planilla = obtenerPlanillaExtrusion($conexion, $id);

    if($planilla && $planilla['estado'] === 'finalizada'){
        echo json_encode(['ok' => true, 'yaHecho' => true, 'pdf_url' => $planilla['ruta_pdf'] ?: null]);
        return;
    }
    if(!$planilla){
        echo json_encode(['ok' => false, 'error' => 'La planilla no está disponible.']);
        return;
    }
    if(!appScriptConfiguradoExtrusion()){
        echo json_encode(['ok' => false, 'error' => 'Falta configurar el Web App de Apps Script de Extrusión.']);
        return;
    }

    // Validar y traducir los pesos
    $segmentos = limpiarSegmentosExtrusion($entrada['segmentos'] ?? []);
    $norm = normalizarRollosExtrusion($conexion, $segmentos);
    if($norm['error']){
        echo json_encode(['ok' => false, 'error' => $norm['error']]);
        return;
    }
    $rollos = $norm['rollos'];
    if(count($rollos) === 0){
        echo json_encode(['ok' => false, 'error' => 'No hay pesos para finalizar el turno.']);
        return;
    }
    guardarBorradorExtrusion($conexion, $id, json_encode($segmentos, JSON_UNESCAPED_UNICODE));

    // Turnos del día para el PDF
    $turnosPdf = [];
    $registrosPrevios = 0;
    foreach(planillasFinalizadasExtrusion($conexion, $planilla['id_maquina'], $planilla['fecha_planilla']) as $otra){
        $detalle = json_decode((string) $otra['filas'], true);
        if(!empty($detalle['rollos'])){
            $turnosPdf[$otra['nombre_turno']] = ['turno' => $otra['nombre_turno'], 'operador' => $otra['nombre_operador'], 'codigo' => $otra['codigo'], 'rollos' => $detalle['rollos']];
            $registrosPrevios += count($detalle['rollos']);
        }
    }
    $turnosPdf[$planilla['nombre_turno']] = ['turno' => $planilla['nombre_turno'], 'operador' => $planilla['nombre_operador'], 'codigo' => $planilla['codigo'], 'rollos' => $rollos];
    $orden = array_keys(turnosExtrusion());
    uksort($turnosPdf, fn($a, $b) => array_search($a, $orden) <=> array_search($b, $orden));

    $pdfBytes = generarPdfExtrusion($planilla['fecha_planilla'], $planilla['nombre_maquina'], array_values($turnosPdf));
    $ahora = date('Y-m-d H:i:s');

    $respuesta = enviarAppScriptExtrusion([
        'accion'    => 'finalizar',
        'registros' => filasRegistrosExtrusion($planilla, $rollos),
        'log'       => [
            'id_dia'       => idDiaExtrusion($planilla['fecha_planilla']),
            'fecha'        => $planilla['fecha_planilla'],
            'maquina'      => $planilla['nombre_maquina'],
            'turno'        => $planilla['nombre_turno'],
            'turno_codigo' => $planilla['codigo'],
            'inicio'       => $planilla['creado_en'],
            'fin'          => $ahora,
            'registros'    => $registrosPrevios + count($rollos),
        ],
        'pdf'       => [
            'nombre'  => nombrePdfExtrusion($planilla['fecha_planilla'], $planilla['nombre_maquina']),
            'maquina' => $planilla['nombre_maquina'],
            'mes'     => date('m-Y', strtotime($planilla['fecha_planilla'])),
            'base64'  => base64_encode($pdfBytes),
        ],
    ]);

    $yaExportado = (!$respuesta['ok'] && ($respuesta['error'] ?? '') === 'yaExportado');
    if(!$respuesta['ok'] && !$yaExportado){
        echo json_encode(['ok' => false, 'error' => $respuesta['error'] ?? 'No se pudo enviar el turno a Google.']);
        return;
    }

    $detalleFinal = json_encode(['rollos' => $rollos], JSON_UNESCAPED_UNICODE);
    cerrarPlanillaExtrusion($conexion, $id, count($rollos), $respuesta['pdf_url'] ?? '', $detalleFinal);

    echo json_encode(['ok' => true, 'yaHecho' => $yaExportado, 'total' => count($rollos), 'pdf_url' => $respuesta['pdf_url'] ?? null]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Ocurrió un error al finalizar el turno. Intenta de nuevo.']);
} finally {
    $conexion->query("SELECT RELEASE_LOCK('{$lockName}')");
}
