<?php
/** @var mysqli $conexion */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';

// Asegurar la tabla del sobre del turno
asegurarTablaPlanillas($conexion);

// Supervisor: id siempre del usuario en sesión; el nombre se puede editar a mano al iniciar el turno
$id_supervisor      = $_SESSION['id_usuario'] ?? null;
$supervisor_nombre  = $_SESSION['usuario'] ?? 'Sin usuario';

$hoy      = date('Y-m-d');
$bloques  = bloquesTurno();

$rutaRegister = BASE_URL . '/modules/sellado/views/register.php';
$rutaHistory  = BASE_URL . '/modules/sellado/views/history.php';

/* =================================================
   INICIAR TURNO (patrón PRG; formulario en el modal de history.php)
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'iniciar'){

    $bloque = $_POST['bloque'] ?? '';
    $fecha  = validarFechaPlanilla($_POST['fecha'] ?? '') ?: $hoy;

    $supervisorManual = trim($_POST['supervisor'] ?? '');
    if($supervisorManual !== ''){
        $supervisor_nombre = $supervisorManual;
    }

    if(!isset($bloques[$bloque])){
        header('Location: ' . $rutaHistory . '?reg_error=' . urlencode('Selecciona un turno válido.'));
        exit;
    }

    $codigo    = construirCodigoPlanilla($fecha, $bloque);
    $existente = obtenerPlanillaPorCodigo($conexion, $codigo);
    $abierta   = obtenerPlanillaAbierta($conexion);

    if($existente && $existente['estado'] === 'finalizada'){
        header('Location: ' . $rutaHistory . '?reg_error=' . urlencode("El turno {$codigo} ya fue finalizado."));
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
    header('Location: ' . $rutaHistory);
    exit;
}

// Catálogos y datos ya guardados del turno
$maquinas    = mysqli_fetch_all(obtenerMaquinasSellado($conexion), MYSQLI_ASSOC);
$operarios   = mysqli_fetch_all(obtenerOperariosActivos($conexion), MYSQLI_ASSOC);
$referencias = obtenerReferenciasOrdenadas($conexion);
$colores     = obtenerColoresOrdenados($conexion);
$datosMaquinas = obtenerPlanillaEstructurada($conexion, $planilla);
$horarioTurno  = $bloques[$planilla['bloque']]['horario'] ?? '';
