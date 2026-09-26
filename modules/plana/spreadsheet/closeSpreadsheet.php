<?php
/** @var mysqli $conexion */

// Reintenta los cierres de día que hayan quedado pendientes (PDF sin confirmar por
// Google). Se llama en segundo plano al abrir el formulario de Máquina Plana. (AJAX, JSON)

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';
require_once __DIR__ . '/appsScript.php';

header('Content-Type: application/json');
set_time_limit(120);

// Sin cola de espera: si ya hay un registro o una reparación en curso, se omite
$tomado = $conexion->query("SELECT GET_LOCK('plana_registrar', 0) AS ok")->fetch_assoc();
if(empty($tomado['ok'])){
    echo json_encode(['ok' => true, 'reparados' => 0, 'omitido' => true]);
    exit;
}

try {
    $reparados = appScriptConfiguradoPlana() ? reintentarCierresPendientesPlana($conexion) : 0;
    echo json_encode(['ok' => true, 'reparados' => $reparados]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false]);
} finally {
    $conexion->query("SELECT RELEASE_LOCK('plana_registrar')");
}
