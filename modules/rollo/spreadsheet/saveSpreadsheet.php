<?php
/** @var mysqli $conexion */

// Registrar producción de Rollos: agrega la fila a REGISTROS y, si la fecha
// avanzó (o se corrige una fecha ya cerrada), cierra el día correspondiente
// generando su PDF y actualizando LOGS. (AJAX, JSON)

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';
require_once __DIR__ . '/appscript.php';

header('Content-Type: application/json');
asegurarTablaLogsRollo($conexion);

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
    [$idOp, $fueCreado] = resolverCatalogoIdONuevo($conexion, 'operarios', 'id_operario', 'nombre_operario', $valorOperario);
    $nombreOperario = $valorOperario;
    if($fueCreado){ $avisoOperario = "Se agregó «{$valorOperario}» como nuevo operario. Falta verificarlo en Catálogos."; }
} else {
    $nombreOperario = null;
}

$idMaquina    = (int) ($entrada['id_maquina'] ?? 0);
$idReferencia = (int) ($entrada['id_referencia'] ?? 0);
$idColor      = (int) ($entrada['id_color'] ?? 0);

$nombreMaquina    = $idMaquina    ? nombreCatalogo($conexion, 'maquinas', 'id_maquina', 'nombre_maquina', $idMaquina) : null;
$nombreReferencia = $idReferencia ? nombreCatalogo($conexion, 'referencias', 'id_referencia', 'nombre_referencia', $idReferencia) : null;
$nombreColor      = $idColor      ? nombreCatalogo($conexion, 'colores', 'id_color', 'nombre_color', $idColor) : null;

if(!$nombreOperario || !$nombreMaquina || !$nombreReferencia || !$nombreColor){
    echo json_encode(['ok' => false, 'error' => 'Operario, máquina, referencia y color son obligatorios.']);
    exit;
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

    // ¿Avanzó la fecha respecto al día que estaba en curso? Si es así, ese día se cierra.
    $cierreAvance = null;
    if($diaActual && $diaActual['id_dia'] !== $idDia && strtotime($fecha) > strtotime($diaActual['fecha'])){
        $cierreAvance = prepararCierreRollo($diaActual);
    }

    // ¿El día que se está registrando ya estaba COMPLETADO? (Caso D: corrección retroactiva)
    $logObjetivoPrevio = obtenerLogPorIdDia($conexion, $idDia);
    $esCorreccionRetroactiva = $logObjetivoPrevio && $logObjetivoPrevio['estado'] === 'completado';

    // Fila a agregar en REGISTROS (columnas B..H)
    $fila = [$fecha, $nombreOperario, $nombreMaquina, $nombreReferencia, $nombreColor, $pesoRollo, $pesoRetal];

    $respuesta = enviarAppScriptRollo(['fila' => $fila, 'cierre' => $cierreAvance]);
    if(!$respuesta['ok']){
        echo json_encode(['ok' => false, 'error' => $respuesta['error'] ?? 'No se pudo registrar en Google.']);
        return;
    }

    // Solo ahora, con la fila ya confirmada en el Sheet, se abre/actualiza el día local
    // (si esto se hiciera antes del envío, un fallo de Apps Script dejaría un día
    // fantasma en_proceso con 0 registros reales)
    abrirDia($conexion, $idDia, $fecha);
    incrementarContadorDia($conexion, $idDia);
    if($cierreAvance){
        cerrarDia($conexion, $cierreAvance['id_dia'], $cierreAvance['total'], $respuesta['cierre_pdf_url'] ?? '');
    }

    $diaCerrado = (bool) $cierreAvance;

    // Caso D: el día recién reabierto se vuelve a cerrar de inmediato con el dato nuevo incluido
    if($esCorreccionRetroactiva){
        $logActualizado = obtenerLogPorIdDia($conexion, $idDia);
        $cierreRetro = prepararCierreRollo($logActualizado);
        $respuestaRetro = enviarAppScriptRollo(['cierre' => $cierreRetro]);
        if($respuestaRetro['ok']){
            cerrarDia($conexion, $idDia, $cierreRetro['total'], $respuestaRetro['cierre_pdf_url'] ?? '');
            $diaCerrado = true;
        }
        // Si falla el recierre, el registro ya quedó guardado en el Sheet; el día
        // simplemente queda en_proceso local para reintentar el cierre más tarde.
    }

    echo json_encode(['ok' => true, 'dia_cerrado' => $diaCerrado, 'aviso' => $avisoOperario]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Ocurrió un error al registrar. Intenta de nuevo.']);
} finally {
    $conexion->query("SELECT RELEASE_LOCK('rollo_registrar')");
}
