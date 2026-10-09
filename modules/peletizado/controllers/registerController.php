<?php
/** @var mysqli $conexion */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';
require_once dirname(__DIR__) . '/spreadsheet/appsScript.php';

$hoy = date('Y-m-d');
$rutaRegister = BASE_URL . '/modules/peletizado/views/register.php';

/* =================================================
   INICIAR TURNO (PRG)
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'iniciar'){
    $fecha = validarFechaPeletizado($_POST['fecha'] ?? '') ?: $hoy;
    $idMaquina = (int) ($_POST['id_maquina'] ?? 0);
    $idTurno   = (int) ($_POST['id_turno'] ?? 0);

    // Solo valores válidos de cada catálogo
    $maquina = null;
    foreach(maquinasPeletizado($conexion) as $m){ if((int) $m['id_maquina'] === $idMaquina){ $maquina = $m; } }
    $turno = null;
    foreach(turnosCatalogoPeletizado($conexion) as $t){ if((int) $t['id_turno'] === $idTurno){ $turno = $t; } }

    if(!$maquina || !$turno){
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode('Completa máquina, fecha y turno.')
            . '&id_maquina=' . $idMaquina);
        exit;
    }

    $codigo = construirCodigoPeletizado($fecha, $turno['nombre_turno']);
    $existente = buscarPlanillaPeletizado($conexion, $codigo, $idMaquina);
    if($existente && $existente['estado'] === 'finalizada'){
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode("El turno {$codigo} de {$maquina['nombre_maquina']} ya fue finalizado.")
            . '&id_maquina=' . $idMaquina);
        exit;
    }
    // La BD local puede haberse reiniciado y no recordarlo: Google es la fuente
    // final, así que también se verifica ahí antes de abrir una planilla nueva.
    if(!$existente && appScriptConfiguradoPeletizado()
        && turnoYaEnRegistrosPeletizado($fecha, $maquina['nombre_maquina'], $turno['nombre_turno'])
    ){
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode("El turno {$codigo} de {$maquina['nombre_maquina']} ya fue finalizado.")
            . '&id_maquina=' . $idMaquina);
        exit;
    }
    $planilla = $existente ?: crearPlanillaPeletizado($conexion, $fecha, $idTurno, $turno['nombre_turno'], $idMaquina);
    header('Location: ' . $rutaRegister . '?id=' . $planilla['id_planilla']);
    exit;
}

/* =================================================
   CANCELAR TURNO (PRG)
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar'){
    cancelarPlanillaPeletizado($conexion, (int) ($_POST['id'] ?? 0));
    $idMaquinaCancelada = (int) ($_POST['id_maquina'] ?? 0);
    header('Location: ' . $rutaRegister . ($idMaquinaCancelada ? '?id_maquina=' . $idMaquinaCancelada : ''));
    exit;
}

/* =================================================
   ESTADO ACTUAL
================================================= */
$planilla = null;
if(isset($_GET['id'])){
    $planilla = obtenerPlanillaPeletizado($conexion, (int) $_GET['id']);
    if($planilla && $planilla['estado'] !== 'abierta'){
        $planilla = null; // ya finalizada
    }
}

if(!$planilla){
    // Sin planilla: primero elegir máquina, luego fecha/turno
    $maquinas = maquinasPeletizado($conexion);
    $idMaquina = (int) ($_GET['id_maquina'] ?? 0);
    $maquinaSeleccionada = null;
    foreach($maquinas as $m){ if((int) $m['id_maquina'] === $idMaquina){ $maquinaSeleccionada = $m; } }

    if($maquinaSeleccionada){
        $turnos = turnosCatalogoPeletizado($conexion);
    }
    $abiertas = planillasAbiertasPeletizado($conexion, $maquinaSeleccionada ? $idMaquina : null);
    return;
}

// Catálogos y borrador de la planilla
$operarios = obtenerOperariosDeArea($conexion, 'peletizado');
$colores   = obtenerColoresDeArea($conexion, 'peletizado');
$borrador  = json_decode((string) $planilla['filas'], true);
if(!is_array($borrador)){
    $borrador = [];
}
$borrador += ['id_operario' => '', 'id_operario2' => '', 'colores' => [], 'observaciones' => ''];
