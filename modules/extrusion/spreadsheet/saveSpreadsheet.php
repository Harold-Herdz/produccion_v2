<?php
/** @var mysqli $conexion */

// Guardado progresivo (AJAX)
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__) . '/models/registerModel.php';

header('Content-Type: application/json');

$entrada = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int) ($entrada['id'] ?? 0);

$planilla = obtenerPlanillaExtrusion($conexion, $id);
if(!$planilla || $planilla['estado'] !== 'abierta'){
    echo json_encode(['ok' => false, 'error' => 'La planilla no está disponible o ya fue finalizada.']);
    exit;
}

try {
    $segmentos = limpiarSegmentosExtrusion($entrada['segmentos'] ?? []);
    guardarBorradorExtrusion($conexion, $id, json_encode($segmentos, JSON_UNESCAPED_UNICODE));
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar. Intenta de nuevo.']);
    exit;
}

echo json_encode(['ok' => true, 'guardado_en' => date('H:i:s')]);
