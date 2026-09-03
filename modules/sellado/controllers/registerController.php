<?php
/** @var mysqli $conexion */

// Importar authMiddleware.php (valida sesión también en acceso directo)
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar registerModel.php
require_once dirname(__DIR__) . '/models/registerModel.php';

// Asegurar la tabla del sobre del turno
asegurarTablaPlanillas($conexion);

// Datos del supervisor desde la sesión (no se edita a mano)
$id_supervisor      = $_SESSION['id_usuario'] ?? null;
$supervisor_nombre  = $_SESSION['usuario'] ?? 'Sin usuario';

// Fecha de hoy en la zona horaria del proyecto
$hoy      = date('Y-m-d');
$bloques  = bloquesTurno();

// Rutas para las redirecciones (el inicio de turno vive en el modal del historial)
$rutaRegister = BASE_URL . '/modules/sellado/views/register.php';
$rutaHistory  = BASE_URL . '/modules/sellado/views/history.php';

/* =================================================
   INICIAR UNA NUEVA PLANILLA (patrón PRG)
   El formulario está en el modal de history.php
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'iniciar'){

    $bloque = $_POST['bloque'] ?? '';
    // La fecha llega del formulario (editable); si no es válida se usa hoy
    $fecha  = validarFechaPlanilla($_POST['fecha'] ?? '') ?: $hoy;

    // Validar el bloque de turno
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
        // Ya hay un turno abierto: llevar directo a esa planilla
        header('Location: ' . $rutaRegister);
        exit;
    }

    // Crear el turno y abrir la planilla
    crearPlanilla($conexion, $fecha, $bloque, $id_supervisor, $supervisor_nombre);
    header('Location: ' . $rutaRegister);
    exit;
}

/* =================================================
   CANCELAR LA PLANILLA ABIERTA (patrón PRG)
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
   ESTADO ACTUAL: ¿HAY UNA PLANILLA ABIERTA?
================================================= */
$planilla = obtenerPlanillaAbierta($conexion);

// Sin turno abierto: el inicio se hace desde el modal del historial
if(!$planilla){
    header('Location: ' . $rutaHistory);
    exit;
}

// Catálogos (como arrays) y datos de la planilla en curso
$maquinas = $operarios = $referencias = $colores = [];
$datosMaquinas = [];
$horarioTurno  = '';

if($planilla){
    // Cargar catálogos para los selectores (misma lógica de catálogos del proyecto)
    $maquinas    = mysqli_fetch_all(obtenerMaquinasSellado($conexion), MYSQLI_ASSOC);
    $operarios   = mysqli_fetch_all(obtenerOperariosActivos($conexion), MYSQLI_ASSOC);
    $referencias = mysqli_fetch_all(obtenerReferenciasActivas($conexion), MYSQLI_ASSOC);
    $colores     = mysqli_fetch_all(obtenerColoresActivos($conexion), MYSQLI_ASSOC);

    // Recuperar lo ya guardado del turno
    $datosMaquinas = obtenerPlanillaEstructurada($conexion, $planilla);
    $horarioTurno  = $bloques[$planilla['bloque']]['horario'] ?? '';
}
