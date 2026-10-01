<?php
/** @var mysqli $conexion */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';
require_once dirname(__DIR__) . '/spreadsheet/appsScript.php';

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
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode("El turno {$codigo} de {$maquina['nombre_maquina']} ya fue finalizado.")
            . '&reabrir_id=' . $existente['id_planilla']);
        exit;
    }
    // La BD local puede haberse reiniciado y no recordarlo: Google es la fuente
    // final, así que también se verifica ahí antes de abrir una planilla nueva.
    if(!$existente && appScriptConfiguradoExtrusion()
        && turnoYaEnRegistrosExtrusion($fecha, $maquina['nombre_maquina'], $turno['nombre_turno'])
    ){
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode("El turno {$codigo} de {$maquina['nombre_maquina']} ya fue finalizado.")
            . '&reabrir_google=1&fecha=' . urlencode($fecha) . '&id_maquina=' . $idMaquina . '&id_turno=' . $idTurno . '&id_operador=' . $idOperador);
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
   REABRIR TURNO YA FINALIZADO (PRG)
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reabrir'){
    $id = (int) ($_POST['id'] ?? 0);
    if(reabrirPlanillaExtrusion($conexion, $id)){
        header('Location: ' . $rutaRegister . '?id=' . $id);
    } else {
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode('No se pudo reabrir ese turno.'));
    }
    exit;
}

/* =================================================
   REABRIR TURNO SOLO CONOCIDO POR GOOGLE (PRG)
   (la BD local no tiene el registro, por ejemplo tras un reset)
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reabrir_google'){
    $fecha      = validarFechaExtrusion($_POST['fecha'] ?? '') ?: $hoy;
    $idMaquina  = (int) ($_POST['id_maquina'] ?? 0);
    $idTurno    = (int) ($_POST['id_turno'] ?? 0);
    $idOperador = (int) ($_POST['id_operador'] ?? 0);

    $maquina = null;
    foreach(maquinasExtrusion($conexion) as $m){ if((int) $m['id_maquina'] === $idMaquina){ $maquina = $m; } }
    $turno = null;
    foreach(turnosCatalogoExtrusion($conexion) as $t){ if((int) $t['id_turno'] === $idTurno){ $turno = $t; } }

    if(!$maquina || !$turno || !$idOperador || !appScriptConfiguradoExtrusion()){
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode('No se pudo reabrir ese turno.'));
        exit;
    }

    $rollosGoogle = rollosDesdeRegistrosExtrusion($fecha, $maquina['nombre_maquina'], $turno['nombre_turno']);
    if(empty($rollosGoogle)){
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode('No se encontraron rollos de ese turno en Google.'));
        exit;
    }

    $planilla = crearPlanillaDesdeGoogleExtrusion($conexion, $fecha, $idTurno, $turno['nombre_turno'], $idMaquina, $idOperador, $rollosGoogle);
    header('Location: ' . $rutaRegister . '?id=' . $planilla['id_planilla']);
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

// Catálogos y borrador de la planilla (referencias según la máquina de la planilla)
$refsMaquina    = referenciasDeMaquinaExtrusion($conexion, $planilla['id_maquina']);
$referencias    = $refsMaquina['normales'];
$referenciasEsp = $refsMaquina['especiales'];
$colores        = obtenerColoresDeArea($conexion, 'extrusion');
$laminas        = laminasExtrusion($conexion);
$borrador       = json_decode((string) $planilla['filas'], true);
if(!is_array($borrador) || !isset($borrador[0]['pesos'])){
    // Turno reabierto: "filas" trae los rollos ya finalizados, no segmentos editables
    $borrador = !empty($borrador['rollos'])
        ? segmentosDesdeRollosExtrusion($borrador['rollos'], $referencias, $referenciasEsp, $colores, $laminas)
        : [];
}
