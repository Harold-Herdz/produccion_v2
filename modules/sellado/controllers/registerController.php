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
$error    = '';

/* =================================================
   INICIAR UNA NUEVA PLANILLA (patrón PRG)
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'iniciar'){

    $bloque = $_POST['bloque'] ?? '';

    // Validar el bloque de turno
    if(!isset($bloques[$bloque])){
        $error = 'Selecciona un turno válido.';
    } else {
        $codigo     = construirCodigoPlanilla($hoy, $bloque);
        $existente  = obtenerPlanillaPorCodigo($conexion, $codigo);
        $abierta    = obtenerPlanillaAbierta($conexion);

        if($existente && $existente['estado'] === 'finalizada'){
            $error = "El turno {$codigo} ya fue finalizado hoy.";
        } elseif($abierta){
            $error = "Ya hay una planilla abierta ({$abierta['codigo']}). Finalízala antes de iniciar otra.";
        } else {
            // Crear el turno y recargar en modo GET
            crearPlanilla($conexion, $hoy, $bloque, $id_supervisor, $supervisor_nombre);
            header('Location: ' . BASE_URL . '/modules/sellado/views/register.php');
            exit;
        }
    }
}

/* =================================================
   ESTADO ACTUAL: ¿HAY UNA PLANILLA ABIERTA?
================================================= */
$planilla = obtenerPlanillaAbierta($conexion);

// Catálogos y datos de la planilla en curso
$maquinas = $operarios = $referencias = $colores = null;
$datosMaquinas = [];
$horarioTurno  = '';

if($planilla){
    // Cargar catálogos para los selectores
    $maquinas    = obtenerMaquinasSellado($conexion);
    $operarios   = obtenerOperariosActivos($conexion);
    $referencias = obtenerReferenciasActivas($conexion);
    $colores     = obtenerColoresActivos($conexion);

    // Recuperar lo ya guardado del turno
    $datosMaquinas = obtenerPlanillaEstructurada($conexion, $planilla['codigo']);
    $horarioTurno  = $bloques[$planilla['bloque']]['horario'] ?? '';
}
