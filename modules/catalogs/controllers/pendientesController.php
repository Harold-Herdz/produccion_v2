<?php
/** @var mysqli $conexion */

/**
 * =====================================================
 *  CAMPANITA DE NOTIFICACIONES — valores de catálogo
 *  escritos a mano ("Otro") pendientes de revisión
 * =====================================================
 *  GET  ?accion=listar   -> JSON con pendientes + historial + contador
 *  POST accion=aprobar   -> confirma un valor (id)
 *  POST accion=rechazar  -> rechaza un valor (id)
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
