<?php
/** @var mysqli $conexion */

// Registrar producción de Rollos: agrega la fila a REGISTROS y, si la fecha
// avanzó (o se corrige una fecha ya cerrada), cierra el día correspondiente
// generando su PDF y actualizando LOGS. (AJAX, JSON)

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';
require_once __DIR__ . '/appsScript.php';

header('Content-Type: application/json');
set_time_limit(120); // registro + cierre(s) implican varias llamadas a Google

$entrada = json_decode(file_get_contents('php://input'), true) ?: [];

// --- Validar ---
$fecha = validarFechaRollo($entrada['fecha'] ?? '');
if(!$fecha){
    echo json_encode(['ok' => false, 'error' => 'Fecha inválida.']);
    exit;
}
// Operario: numérico = id existente; texto = "Otro" escrito a mano (se busca o se crea)
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

// Referencia: numérica = id de la lista de esa máquina; texto = "Otro" escrito a mano
[$idReferencia, $avisoReferencia] = resolverValorCatalogo(
    $conexion, 'referencias', 'id_referencia', 'nombre_referencia',
    $entrada['id_referencia'] ?? '', 'nueva referencia', '', 'rollo'
);
$nombreReferencia = $idReferencia ? nombreCatalogo($conexion, 'referencias', 'id_referencia', 'nombre_referencia', $idReferencia) : null;

// Color: numérico = id existente; texto = "Otro" escrito a mano
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

    // Reglas de cierre (se evalúan ANTES de registrar, con el estado actual):
    //  - Fecha nueva (posterior al día en curso): el día en curso se cierra.
    //  - Fecha pasada (anterior al día en curso, ya cerrada o nunca abierta): ese día se
    //    vuelve a cerrar al final para que su PDF incluya el registro nuevo.
    $diaACerrar = null;
    $esDiaPasado = false;
    if($diaActual && $diaActual['id_dia'] !== $idDia){
        if(strtotime($fecha) > strtotime($diaActual['fecha'])){
            $diaACerrar = $diaActual;
        } elseif(strtotime($fecha) < strtotime($diaActual['fecha'])){
            $esDiaPasado = true;
        }
    }
    // Día ya COMPLETADO al que se le agrega un registro (aunque no haya un día en curso)
    $logObjetivoPrevio = obtenerLogPorIdDia($conexion, $idDia);
    $recerrarDia = $esDiaPasado || ($logObjetivoPrevio && $logObjetivoPrevio['estado'] === 'completado');

    // Fila a agregar en REGISTROS (columnas B..H)
    $fila = [$fecha, $nombreOperario, $nombreMaquina, $nombreReferencia, $nombreColor, $pesoRollo, $pesoRetal];

    // Id único (reservado para futuro chequeo en Apps Script)
    $idRegistro = bin2hex(random_bytes(8));

    // 1) SOLO el registro. El cierre va aparte: así un fallo del cierre nunca se confunde
    //    con que el registro se guardó, ni al revés.
    $respuesta = enviarAppScriptRollo(['fila' => $fila, 'id_registro' => $idRegistro]);

    if(!$respuesta['ok']){
        // Respuesta no interpretable: confirma si ya se guardó
        if(!yaExisteRegistroRollo($fecha, $nombreOperario, $nombreMaquina, $nombreReferencia, $nombreColor, $pesoRollo, $pesoRetal)){
            echo json_encode(['ok' => false, 'error' => $respuesta['error'] ?? 'No se pudo registrar en Google.']);
            return;
        }
    }

    // Solo ahora, con la fila ya confirmada en el Sheet, se abre/actualiza el día local
    // (si esto se hiciera antes del envío, un fallo de Apps Script dejaría un día
    // fantasma en_proceso con 0 registros reales)
    abrirDia($conexion, $idDia, $fecha);
    incrementarContadorDia($conexion, $idDia);

    // 2) Cierres. Un día solo queda cerrado en local cuando Google confirma el PDF; si no,
    //    queda pendiente y se reintenta solo (aquí mismo en el próximo registro, o al abrir
    //    el formulario).
    $diaCerrado = false;
    if($diaACerrar){
        $diaCerrado = cerrarDiaConfirmado($conexion, $diaACerrar);
    }
    if($recerrarDia){
        // El contador local ya incluye el registro nuevo: el PDF espera verlo en el Sheet
        $diaCerrado = cerrarDiaConfirmado($conexion, obtenerLogPorIdDia($conexion, $idDia)) || $diaCerrado;
    }

    // Auto-reparación de cierres anteriores que hayan quedado pendientes
    try { reintentarCierresPendientes($conexion); } catch (Throwable $e) { /* no afecta el registro ya guardado */ }

    echo json_encode(['ok' => true, 'dia_cerrado' => $diaCerrado, 'aviso' => $avisoOperario]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Ocurrió un error al registrar. Intenta de nuevo.']);
} finally {
    $conexion->query("SELECT RELEASE_LOCK('rollo_registrar')");
}
