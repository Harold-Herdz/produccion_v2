<?php
/** @var mysqli $conexion */

// Registra producción y cierra días

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';
require_once __DIR__ . '/appsScript.php';

header('Content-Type: application/json');
set_time_limit(120); // varias llamadas a Google

$entrada = json_decode(file_get_contents('php://input'), true) ?: [];

// --- Validar ---
$fecha = validarFechaRollo($entrada['fecha'] ?? '');
if(!$fecha){
    echo json_encode(['ok' => false, 'error' => 'Fecha inválida.']);
    exit;
}
// Operario: id o texto Otro
$avisoOperario = null;
$valorOperario = trim((string) ($entrada['id_operario'] ?? ''));
if($valorOperario !== '' && $valorOperario !== 'otro' && is_numeric($valorOperario)){
    $nombreOperario = nombreCatalogo($conexion, 'operarios', 'id_operario', 'nombre_operario', (int) $valorOperario);
} elseif($valorOperario !== '' && $valorOperario !== 'otro'){
    if(!nombrePropioValido($valorOperario)){
        echo json_encode(['ok' => false, 'error' => 'El nombre del operario solo puede tener letras.']);
        exit;
    }
    // Capitaliza cada palabra
    $valorOperario = capitalizarNombre($valorOperario);
    [$idOp, $fueCreado] = resolverCatalogoIdONuevo($conexion, 'operarios', 'id_operario', 'nombre_operario', $valorOperario);
    $nombreOperario = $valorOperario;
    if($fueCreado){
        $avisoOperario = "Se agregó «{$valorOperario}» como nuevo operario. Falta verificarlo en Catálogos.";
        registrarCatalogoPendiente($conexion, 'operarios', $idOp, $valorOperario, 'nuevo operario', '', 'rollo');
    }
} else {
    $nombreOperario = null;
}

$idMaquina     = (int) ($entrada['id_maquina'] ?? 0);
$nombreMaquina = $idMaquina ? nombreCatalogo($conexion, 'maquinas', 'id_maquina', 'nombre_maquina', $idMaquina) : null;

// Referencia: id o texto Otro
[$idReferencia, $avisoReferencia] = resolverValorCatalogo(
    $conexion, 'referencias', 'id_referencia', 'nombre_referencia',
    $entrada['id_referencia'] ?? '', 'nueva referencia', '', 'rollo'
);
$nombreReferencia = $idReferencia ? nombreCatalogo($conexion, 'referencias', 'id_referencia', 'nombre_referencia', $idReferencia) : null;

// Color: id o texto Otro
[$idColor, $avisoColor] = resolverValorCatalogo(
    $conexion, 'colores', 'id_color', 'nombre_color',
    $entrada['id_color'] ?? '', 'nuevo color', '', 'rollo'
);
$nombreColor = $idColor ? nombreCatalogo($conexion, 'colores', 'id_color', 'nombre_color', $idColor) : null;

if(!$nombreOperario || !$nombreMaquina || !$nombreReferencia || !$nombreColor){
    echo json_encode(['ok' => false, 'error' => 'Operario, máquina, referencia y color son obligatorios.']);
    exit;
}

$avisoExtra = $avisoReferencia ?: $avisoColor;
if($avisoExtra){
    $avisoOperario = $avisoOperario ? $avisoOperario . ' ' . $avisoExtra : $avisoExtra;
}

$pesoRollo = pesoRollo($entrada['peso_rollo'] ?? '');
$pesoRetal = pesoRollo($entrada['peso_retal'] ?? '');
if($pesoRollo === null || $pesoRetal === null){
    echo json_encode(['ok' => false, 'error' => 'Los pesos no pueden ser negativos.']);
    exit;
}

if(!appScriptConfiguradoRollo()){
    echo json_encode(['ok' => false, 'error' => 'Falta configurar el Web App de Apps Script de Rollos.']);
    exit;
}

$conexion->query("SELECT GET_LOCK('rollo_registrar', 15)");

try {
    $idDia = construirIdDia($fecha);
    $diaActual = obtenerDiaEnProceso($conexion);

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
    $logObjetivoPrevio = obtenerLogPorIdDia($conexion, $idDia);
    $recerrarDia = $esDiaPasado || ($logObjetivoPrevio && $logObjetivoPrevio['estado'] === 'completado');

    // Fila para REGISTROS (B..H)
    $fila = [$fecha, $nombreOperario, $nombreMaquina, $nombreReferencia, $nombreColor, $pesoRollo, $pesoRetal];

    // Id único del registro
    $idRegistro = bin2hex(random_bytes(8));

    // 1) Solo el registro; cierre aparte
    $respuesta = enviarAppScriptRollo(['fila' => $fila, 'id_registro' => $idRegistro]);

    if(!$respuesta['ok']){
        // Confirmar si ya se guardó
        if(!yaExisteRegistroRollo($fecha, $nombreOperario, $nombreMaquina, $nombreReferencia, $nombreColor, $pesoRollo, $pesoRetal)){
            echo json_encode(['ok' => false, 'error' => $respuesta['error'] ?? 'No se pudo registrar en Google.']);
            return;
        }
    }

    // Con la fila confirmada, abrir día
    abrirDia($conexion, $idDia, $fecha);
    incrementarContadorDia($conexion, $idDia);

    // 2) Cierres (solo con PDF confirmado)
    $diaCerrado = false;
    if($diaACerrar){
        $diaCerrado = cerrarDiaConfirmado($conexion, $diaACerrar);
    }
    if($recerrarDia){
        // Recerrar con el registro nuevo
        $diaCerrado = cerrarDiaConfirmado($conexion, obtenerLogPorIdDia($conexion, $idDia)) || $diaCerrado;
    }

    // Reparar cierres pendientes
    try { reintentarCierresPendientes($conexion); } catch (Throwable $e) { /* no afecta lo guardado */ }

    echo json_encode(['ok' => true, 'dia_cerrado' => $diaCerrado, 'aviso' => $avisoOperario]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Ocurrió un error al registrar. Intenta de nuevo.']);
} finally {
    $conexion->query("SELECT RELEASE_LOCK('rollo_registrar')");
}
