<?php
/** @var mysqli $conexion */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';

$hoy = date('Y-m-d');
$rutaRegister = BASE_URL . '/modules/extrusion/views/register.php';

/* =================================================
   INICIAR TURNO (PRG)
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'iniciar'){
    $fecha = validarFechaExtrusion($_POST['fecha'] ?? '') ?: $hoy;
    $idMaquina  = (int) ($_POST['id_maquina'] ?? 0);
    $idTurno    = (int) ($_POST['id_turno'] ?? 0);
    $idOperador = (int) ($_POST['id_operador'] ?? 0);

    // Solo valores válidos de cada catálogo
    $maquina = null;
    foreach(maquinasExtrusion($conexion) as $m){ if((int) $m['id_maquina'] === $idMaquina){ $maquina = $m; } }
    $turno = null;
    foreach(turnosCatalogoExtrusion($conexion) as $t){ if((int) $t['id_turno'] === $idTurno){ $turno = $t; } }
    $operador = null;
    foreach(operadoresExtrusion($conexion) as $o){ if((int) $o['id_operador'] === $idOperador){ $operador = $o; } }

    if(!$maquina || !$turno || !$operador){
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode('Completa máquina, turno y operador.'));
        exit;
    }

    $codigo = construirCodigoExtrusion($fecha, $turno['nombre_turno']);
    $existente = buscarPlanillaExtrusion($conexion, $codigo, $idMaquina);
    if($existente && $existente['estado'] === 'finalizada'){
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode("El turno {$codigo} de {$maquina['nombre_maquina']} ya fue finalizado."));
        exit;
    }
    $planilla = $existente ?: crearPlanillaExtrusion($conexion, $fecha, $idTurno, $turno['nombre_turno'], $idMaquina, $idOperador);
    header('Location: ' . $rutaRegister . '?id=' . $planilla['id_planilla']);
    exit;
}

/* =================================================
   CANCELAR TURNO (PRG)
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar'){
    cancelarPlanillaExtrusion($conexion, (int) ($_POST['id'] ?? 0));
    header('Location: ' . $rutaRegister);
    exit;
}

/* =================================================
   ESTADO ACTUAL
================================================= */
$planilla = null;
if(isset($_GET['id'])){
    $planilla = obtenerPlanillaExtrusion($conexion, (int) $_GET['id']);
    if($planilla && $planilla['estado'] !== 'abierta'){
        $planilla = null; // ya finalizada
    }
}

if(!$planilla){
    // Sin planilla: formulario de inicio + turnos abiertos
    $maquinas    = maquinasExtrusion($conexion);
    $turnos      = turnosCatalogoExtrusion($conexion);
    $operadores  = operadoresExtrusion($conexion);
    $abiertas    = planillasAbiertasExtrusion($conexion);
    return;
}

// Catálogos y borrador de la planilla
$referencias    = referenciasExtrusion($conexion);
$referenciasEsp = obtenerReferenciasEspOrdenadas($conexion);
$colores        = obtenerColoresOrdenados($conexion);
$laminas        = laminasExtrusion($conexion);
$borrador       = json_decode((string) $planilla['filas'], true);
if(!is_array($borrador) || !isset($borrador[0]['pesos'])){
    $borrador = [];
}
