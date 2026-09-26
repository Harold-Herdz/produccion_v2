<?php
/** @var mysqli $conexion */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';

$hoy      = date('Y-m-d');
$bloques  = bloquesTurno();

$rutaRegister = BASE_URL . '/modules/sellado/views/register.php';

/* =================================================
   INICIAR TURNO (patrón PRG; formulario de inicio en register.php)
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'iniciar'){

    $bloque = $_POST['bloque'] ?? '';
    $fecha  = validarFechaPlanilla($_POST['fecha'] ?? '') ?: $hoy;

    if(!isset($bloques[$bloque])){
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode('Selecciona un turno válido.'));
        exit;
    }

    $supervisor = obtenerOperarioSupervisorPorId($conexion, $_POST['id_supervisor'] ?? 0);
    if(!$supervisor){
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode('Selecciona un supervisor válido.'));
        exit;
    }
    $id_supervisor     = $supervisor['id_operario'];
    $supervisor_nombre = $supervisor['nombre_operario'];

    $codigo    = construirCodigoPlanilla($fecha, $bloque);
    $existente = obtenerPlanillaPorCodigo($conexion, $codigo);

    if($existente && $existente['estado'] === 'finalizada'){
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode("El turno {$codigo} ya fue finalizado."));
        exit;
    }
    // Ya abierto: continuar; si no, crear uno nuevo
    if(!$existente){
        crearPlanilla($conexion, $fecha, $bloque, $id_supervisor, $supervisor_nombre);
    }
    header('Location: ' . $rutaRegister . '?codigo=' . urlencode($codigo));
    exit;
}

/* =================================================
   CANCELAR TURNO (patrón PRG)
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar'){
    $abierta = obtenerPlanillaPorCodigo($conexion, $_POST['codigo'] ?? '');
    if($abierta && $abierta['estado'] === 'abierta'){
        cancelarPlanilla($conexion, $abierta);
    }
    header('Location: ' . $rutaRegister);
    exit;
}

/* =================================================
   ESTADO ACTUAL
================================================= */
$planilla = null;
if(!empty($_GET['codigo'])){
    $candidata = obtenerPlanillaPorCodigo($conexion, $_GET['codigo']);
    if($candidata && $candidata['estado'] === 'abierta'){
        $planilla = $candidata;
    }
}

if(!$planilla){
    // Sin planilla: formulario de inicio y turnos abiertos
    $supervisores = mysqli_fetch_all(obtenerOperariosSupervisores($conexion), MYSQLI_ASSOC);
    $abiertas     = listarPlanillasAbiertas($conexion);
    return;
}

// Catálogos y datos del turno
$datosMaquina  = obtenerMaquinasConReferencias($conexion, 'sellado');
$maquinas      = $datosMaquina['maquinas'];
$mapaReferenciasMaquina = $datosMaquina['mapaJs'];
$operarios   = mysqli_fetch_all(obtenerOperariosActivos($conexion), MYSQLI_ASSOC);
$colores     = obtenerColoresOrdenados($conexion);
$datosMaquinas = obtenerPlanillaEstructurada($conexion, $planilla);
$horarioTurno  = $bloques[$planilla['bloque']]['horario'] ?? '';
