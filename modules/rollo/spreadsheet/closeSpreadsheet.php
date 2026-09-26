<?php
/** @var mysqli $conexion */

// Reintenta cierres de día pendientes

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';
require_once __DIR__ . '/appsScript.php';

header('Content-Type: application/json');
set_time_limit(120);

// Sin espera; omitir si ocupado
$tomado = $conexion->query("SELECT GET_LOCK('rollo_registrar', 0) AS ok")->fetch_assoc();
if(empty($tomado['ok'])){
    echo json_encode(['ok' => true, 'reparados' => 0, 'omitido' => true]);
    exit;
}

try {
    $reparados = appScriptConfiguradoRollo() ? reintentarCierresPendientes($conexion) : 0;
    echo json_encode(['ok' => true, 'reparados' => $reparados]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false]);
} finally {
    $conexion->query("SELECT RELEASE_LOCK('rollo_registrar')");
}
