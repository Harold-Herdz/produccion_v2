<?php
/** @var mysqli $conexion */

// Guardado progresivo de la planilla (AJAX, JSON)

// Importar authMiddleware.php (valida sesión)
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar registerModel.php
require_once dirname(__DIR__) . '/models/registerModel.php';

header('Content-Type: application/json');

// Leer el cuerpo JSON
$entrada  = json_decode(file_get_contents('php://input'), true) ?: [];
$codigo   = $entrada['codigo'] ?? '';
$maquinas = $entrada['maquinas'] ?? [];

// Verificar que la planilla exista y siga abierta
$planilla = obtenerPlanillaPorCodigo($conexion, $codigo);
if(!$planilla || $planilla['estado'] !== 'abierta'){
    echo json_encode([
        'ok'    => false,
        'error' => 'La planilla no está disponible o ya fue finalizada.'
    ]);
    exit;
}

// Si el supervisor cambió la fecha, recodificar la planilla
$fecha = validarFechaPlanilla($entrada['fecha'] ?? '');
if($fecha){
    [$planilla, $errFecha] = recodificarPlanilla($conexion, $planilla, $fecha);
    if($errFecha){
        echo json_encode(['ok' => false, 'error' => $errFecha]);
        exit;
    }
}

// Guardar los datos recibidos
try {
    $resultado = guardarPlanilla($conexion, $planilla, $maquinas);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar. Revisa los datos e intenta de nuevo.']);
    exit;
}

echo json_encode([
    'ok'          => true,
    'codigo'      => $planilla['codigo'],
    'guardado_en' => date('H:i:s'),
    'guardados'   => $resultado['guardados'],
    'avisos'      => $resultado['avisos'],
]);
