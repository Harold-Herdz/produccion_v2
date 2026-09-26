<?php
/** @var mysqli $conexion */

// Registrar pesos de Máquina Plana: agrega la fila a REGISTROS y, si la fecha
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
$fecha = validarFechaPlana($entrada['fecha'] ?? '');
if(!$fecha){
    echo json_encode(['ok' => false, 'error' => 'Fecha inválida.']);
    exit;
}
// Operario: numérico = id existente; texto = "Otro" escrito a mano (se busca o se crea)
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

// Máquina: solo las habilitadas para Plana (Relaciones > Máquinas × Áreas)
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

// Referencia (especial): numérica = id de la lista; texto = "Otro" escrito a mano
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
    $logObjetivoPrevio = obtenerLogPorIdDiaPlana($conexion, $idDia);
    $recerrarDia = $esDiaPasado || ($logObjetivoPrevio && $logObjetivoPrevio['estado'] === 'completado');

    // Fila a agregar en REGISTROS (columnas B..I)
    $fila = [$fecha, $nombreOperario, $nombreMaquina, $nombreReferencia, $pesoRollo, $pesoRetal, $bultos, $pesoTotal];

    // Id único (reservado para futuro chequeo en Apps Script)
    $idRegistro = bin2hex(random_bytes(8));

    // 1) SOLO el registro. El cierre va aparte: así un fallo del cierre nunca se confunde
    //    con que el registro se guardó, ni al revés.
    $respuesta = enviarAppScriptPlana(['fila' => $fila, 'id_registro' => $idRegistro]);

    if(!$respuesta['ok']){
        // Respuesta no interpretable: confirma si ya se guardó
        if(!yaExisteRegistroPlana($fecha, $nombreOperario, $nombreMaquina, $nombreReferencia, $pesoRollo, $pesoRetal, $bultos, $pesoTotal)){
            echo json_encode(['ok' => false, 'error' => $respuesta['error'] ?? 'No se pudo registrar en Google.']);
            return;
        }
    }

    // Solo ahora, con la fila ya confirmada en el Sheet, se abre/actualiza el día local
    abrirDiaPlana($conexion, $idDia, $fecha);
    incrementarContadorDiaPlana($conexion, $idDia);

    // 2) Cierres. Un día solo queda cerrado en local cuando Google confirma el PDF; si no,
    //    queda pendiente y se reintenta solo (aquí mismo en el próximo registro, o al abrir
    //    el formulario).
    $diaCerrado = false;
    if($diaACerrar){
        $diaCerrado = cerrarDiaConfirmadoPlana($conexion, $diaACerrar);
    }
    if($recerrarDia){
        // El contador local ya incluye el registro nuevo: el PDF espera verlo en el Sheet
        $diaCerrado = cerrarDiaConfirmadoPlana($conexion, obtenerLogPorIdDiaPlana($conexion, $idDia)) || $diaCerrado;
    }

    // Auto-reparación de cierres anteriores que hayan quedado pendientes
    try { reintentarCierresPendientesPlana($conexion); } catch (Throwable $e) { /* no afecta el registro ya guardado */ }

    echo json_encode(['ok' => true, 'dia_cerrado' => $diaCerrado, 'aviso' => $avisoOperario]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Ocurrió un error al registrar. Intenta de nuevo.']);
} finally {
    $conexion->query("SELECT RELEASE_LOCK('plana_registrar')");
}
