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
    $abierta   = obtenerPlanillaAbierta($conexion);

    if($existente && $existente['estado'] === 'finalizada'){
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode("El turno {$codigo} ya fue finalizado."));
        exit;
    }
    if($abierta){
        // Ya hay un turno abierto: ir directo a esa planilla
        header('Location: ' . $rutaRegister);
        exit;
    }

    crearPlanilla($conexion, $fecha, $bloque, $id_supervisor, $supervisor_nombre);
    header('Location: ' . $rutaRegister);
    exit;
}

/* =================================================
   CANCELAR TURNO (patrón PRG)
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar'){
    $abierta = obtenerPlanillaAbierta($conexion);
    if($abierta){
        cancelarPlanilla($conexion, $abierta);
    }
    header('Location: ' . $rutaRegister);
    exit;
}

/* =================================================
   ESTADO ACTUAL
================================================= */
$planilla = obtenerPlanillaAbierta($conexion);

if(!$planilla){
    // Sin turno abierto: register.php muestra el formulario de inicio
    $supervisores = mysqli_fetch_all(obtenerOperariosSupervisores($conexion), MYSQLI_ASSOC);
    return;
}

// Catálogos y datos ya guardados del turno
$datosMaquina  = obtenerMaquinasConReferencias($conexion, 'sellado');
$maquinas      = $datosMaquina['maquinas'];
$mapaReferenciasMaquina = $datosMaquina['mapaJs'];
$operarios   = mysqli_fetch_all(obtenerOperariosActivos($conexion), MYSQLI_ASSOC);
$colores     = obtenerColoresOrdenados($conexion);
$datosMaquinas = obtenerPlanillaEstructurada($conexion, $planilla);
$horarioTurno  = $bloques[$planilla['bloque']]['horario'] ?? '';
