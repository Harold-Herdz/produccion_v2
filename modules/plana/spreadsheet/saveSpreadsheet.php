<?php
/** @var mysqli $conexion */

// Registra pesos y cierra días

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';
require_once __DIR__ . '/appsScript.php';

header('Content-Type: application/json');
set_time_limit(120); // varias llamadas a Google

$entrada = json_decode(file_get_contents('php://input'), true) ?: [];

// --- Validar ---
$fecha = validarFechaPlana($entrada['fecha'] ?? '');
if(!$fecha){
    echo json_encode(['ok' => false, 'error' => 'Fecha inválida.']);
    exit;
}
// Operario: id o texto Otro
$avisoOperario = null;
$valorOperario = trim((string) ($entrada['id_operario'] ?? ''));
if($valorOperario !== '' && $valorOperario !== 'otro' && is_numeric($valorOperario)){
    $nombreOperario = nombreCatalogoPlana($conexion, 'operarios', 'id_operario', 'nombre_operario', (int) $valorOperario);
} elseif($valorOperario !== '' && $valorOperario !== 'otro'){
    if(!nombrePropioValidoPlana($valorOperario)){
        echo json_encode(['ok' => false, 'error' => 'El nombre del operario solo puede tener letras.']);
        exit;
    }
    $valorOperario = capitalizarNombrePlana($valorOperario);
    [$idOp, $fueCreado] = resolverCatalogoIdONuevo($conexion, 'operarios', 'id_operario', 'nombre_operario', $valorOperario);
    $nombreOperario = $valorOperario;
    if($fueCreado){
        $avisoOperario = "Se agregó «{$valorOperario}» como nuevo operario. Falta verificarlo en Catálogos.";
        registrarCatalogoPendiente($conexion, 'operarios', $idOp, $valorOperario, 'nuevo operario', '', 'plana');
    }
} else {
    $nombreOperario = null;
}

// Solo máquinas de Plana
$idMaquina = (int) ($entrada['id_maquina'] ?? 0);
$nombreMaquina = null;
if($idMaquina){
    $datosMaquinas = obtenerMaquinasConReferencias($conexion, 'plana');
    foreach($datosMaquinas['maquinas'] as $m){
        if((int) $m['id_maquina'] === $idMaquina){
            $nombreMaquina = $m['nombre_maquina'];
        }
    }
}

// Referencia: id o texto Otro
[$idReferencia, $avisoReferencia] = resolverValorCatalogo(
    $conexion, 'referencias_esp', 'id_referencia_esp', 'nombre_referencia_esp',
    $entrada['id_referencia'] ?? '', 'nueva referencia especial', '', 'plana'
);
$nombreReferencia = $idReferencia ? nombreCatalogoPlana($conexion, 'referencias_esp', 'id_referencia_esp', 'nombre_referencia_esp', $idReferencia) : null;

if(!$nombreOperario || !$nombreMaquina || !$nombreReferencia){
    echo json_encode(['ok' => false, 'error' => 'Operario, máquina y referencia son obligatorios.']);
    exit;
}
if($avisoReferencia){
    $avisoOperario = $avisoOperario ? $avisoOperario . ' ' . $avisoReferencia : $avisoReferencia;
}

$pesoRollo = pesoPlana($entrada['peso_rollo'] ?? '');
$pesoRetal = pesoPlana($entrada['peso_retal'] ?? '');
$pesoTotal = pesoPlana($entrada['peso_total'] ?? '');
$bultos    = bultosPlana($entrada['bultos'] ?? '');
if($pesoRollo === null || $pesoRetal === null || $pesoTotal === null){
    echo json_encode(['ok' => false, 'error' => 'Los pesos no pueden ser negativos.']);
    exit;
}
if($bultos === null){
    echo json_encode(['ok' => false, 'error' => 'Los bultos deben ser un número entero.']);
    exit;
}

if(!appScriptConfiguradoPlana()){
    echo json_encode(['ok' => false, 'error' => 'Falta configurar el Web App de Apps Script de Máquina Plana.']);
    exit;
}

$conexion->query("SELECT GET_LOCK('plana_registrar', 15)");

try {
    $idDia = construirIdDiaPlana($fecha);
    $diaActual = obtenerDiaEnProcesoPlana($conexion);

    // Reglas de cierre (antes de registrar)
    // Fecha nueva: cierra el día en curso
    // Fecha pasada: recierra ese día
    $diaACerrar = null;
    $esDiaPasado = false;
    if($diaActual && $diaActual['id_dia'] !== $idDia){
        if(strtotime($fecha) > strtotime($diaActual['fecha'])){
            $diaACerrar = $diaActual;
        } elseif(strtotime($fecha) < strtotime($diaActual['fecha'])){
            $esDiaPasado = true;
        }
    }
    // Día completado que recibe registro
    $logObjetivoPrevio = obtenerLogPorIdDiaPlana($conexion, $idDia);
    $recerrarDia = $esDiaPasado || ($logObjetivoPrevio && $logObjetivoPrevio['estado'] === 'completado');

    // Fila para REGISTROS (B..I)
    $fila = [$fecha, $nombreOperario, $nombreMaquina, $nombreReferencia, $pesoRollo, $pesoRetal, $bultos, $pesoTotal];

    // Id único del registro
    $idRegistro = bin2hex(random_bytes(8));

    // 1) Solo el registro; cierre aparte
    $respuesta = enviarAppScriptPlana(['fila' => $fila, 'id_registro' => $idRegistro]);

    if(!$respuesta['ok']){
        // Confirmar si ya se guardó
        if(!yaExisteRegistroPlana($fecha, $nombreOperario, $nombreMaquina, $nombreReferencia, $pesoRollo, $pesoRetal, $bultos, $pesoTotal)){
            echo json_encode(['ok' => false, 'error' => $respuesta['error'] ?? 'No se pudo registrar en Google.']);
            return;
        }
    }

    // Con la fila confirmada, abrir día
    abrirDiaPlana($conexion, $idDia, $fecha);
    incrementarContadorDiaPlana($conexion, $idDia);

    // 2) Cierres (solo con PDF confirmado)
    $diaCerrado = false;
    if($diaACerrar){
        $diaCerrado = cerrarDiaConfirmadoPlana($conexion, $diaACerrar);
    }
    if($recerrarDia){
        // Recerrar con el registro nuevo
        $diaCerrado = cerrarDiaConfirmadoPlana($conexion, obtenerLogPorIdDiaPlana($conexion, $idDia)) || $diaCerrado;
    }

    // Reparar cierres pendientes
    try { reintentarCierresPendientesPlana($conexion); } catch (Throwable $e) { /* no afecta lo guardado */ }

    echo json_encode(['ok' => true, 'dia_cerrado' => $diaCerrado, 'aviso' => $avisoOperario]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Ocurrió un error al registrar. Intenta de nuevo.']);
} finally {
    $conexion->query("SELECT RELEASE_LOCK('plana_registrar')");
}
