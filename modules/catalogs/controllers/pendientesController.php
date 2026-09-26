<?php
/** @var mysqli $conexion */

/**
 * =====================================================
 * Campanita de notificaciones
 * =====================================================
 * GET listar: pendientes e historial
 * POST aprobar
 * POST rechazar
 */

$soloAdmin = true;

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__) . '/models/pendientesModel.php';

header('Content-Type: application/json');

$accion = $_POST['accion'] ?? $_GET['accion'] ?? 'listar';

if($accion === 'aprobar' || $accion === 'rechazar'){
    $id = (int) ($_POST['id'] ?? 0);
    $ok = ($accion === 'aprobar') ? aprobarCatalogoPendiente($conexion, $id) : rechazarCatalogoPendiente($conexion, $id);
    echo json_encode(['ok' => $ok]);
    exit;
}

// Listar (por defecto)
$datos = listarCatalogoPendientes($conexion);
echo json_encode([
    'ok'         => true,
    'contador'   => count($datos['pendientes']),
    'pendientes' => $datos['pendientes'],
    'historial'  => $datos['historial'],
]);
