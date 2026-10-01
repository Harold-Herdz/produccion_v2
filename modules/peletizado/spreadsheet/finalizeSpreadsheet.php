<?php
/** @var mysqli $conexion */

// Finaliza turno (AJAX)
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';
require_once __DIR__ . '/pdfSpreadsheet.php';
require_once __DIR__ . '/appsScript.php';

header('Content-Type: application/json');
set_time_limit(240);

$entrada = json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int) ($entrada['id'] ?? 0);

// Candado anti doble finalización
$lockName = 'peletizado_planilla_' . $id;
$conexion->query("SELECT GET_LOCK('{$lockName}', 15)");

try {
    $planilla = obtenerPlanillaPeletizado($conexion, $id);

    if($planilla && $planilla['estado'] === 'finalizada'){
        echo json_encode(['ok' => true, 'yaHecho' => true, 'pdf_url' => $planilla['ruta_pdf'] ?: null]);
        return;
    }
    if(!$planilla){
        echo json_encode(['ok' => false, 'error' => 'La planilla no está disponible.']);
        return;
    }
    if(!appScriptConfiguradoPeletizado()){
        echo json_encode(['ok' => false, 'error' => 'Falta configurar el Web App de Apps Script de Peletizado.']);
        return;
    }

    // Validar y resolver el borrador (nombres de operarios y colores)
    $borrador = limpiarBorradorPeletizado($entrada['borrador'] ?? []);
    guardarBorradorPeletizado($conexion, $id, json_encode($borrador, JSON_UNESCAPED_UNICODE));
    $norm = normalizarBorradorPeletizado($conexion, $borrador);
    if($norm['error']){
        echo json_encode(['ok' => false, 'error' => $norm['error']]);
        return;
    }
    $filas = $norm['filas'];
    $operario1 = $norm['operario1'];
    $operario2 = $norm['operario2'];
    $observaciones = $norm['observaciones'];
    $totalTurno = count($filas) > 0 ? count($filas) : 1; // turno vacío = 1 registro de constancia

    // Turnos del día para el PDF
    $turnosPdf = [];
    $registrosPrevios = 0;
    foreach(planillasFinalizadasPeletizado($conexion, $planilla['id_maquina'], $planilla['fecha_planilla']) as $otra){
        $detalle = json_decode((string) $otra['filas'], true);
        if(is_array($detalle)){
            $turnosPdf[$otra['nombre_turno']] = [
                'turno' => $otra['nombre_turno'], 'codigo' => $otra['codigo'],
                'operario1' => $detalle['operario1'] ?? '', 'operario2' => $detalle['operario2'] ?? '',
                'observaciones' => $detalle['observaciones'] ?? '', 'filas' => $detalle['filas'] ?? [],
            ];
            $registrosPrevios += max(1, count($detalle['filas'] ?? []));
        }
    }
    $turnosPdf[$planilla['nombre_turno']] = [
        'turno' => $planilla['nombre_turno'], 'codigo' => $planilla['codigo'],
        'operario1' => $operario1, 'operario2' => $operario2, 'observaciones' => $observaciones, 'filas' => $filas,
    ];
    $orden = array_keys(turnosPeletizado());
    uksort($turnosPdf, fn($a, $b) => array_search($a, $orden) <=> array_search($b, $orden));

    $pdfBytes = generarPdfPeletizado($planilla['fecha_planilla'], $planilla['nombre_maquina'], array_values($turnosPdf));
    $ahora = date('Y-m-d H:i:s');

    $respuesta = enviarAppScriptPeletizado([
        'accion'    => 'finalizar',
        'registros' => filasRegistrosPeletizado($planilla, $filas, $operario1, $operario2, $observaciones),
        'log'       => [
            'id_dia'    => idDiaPeletizado($planilla['fecha_planilla']),
            'fecha'     => $planilla['fecha_planilla'],
            'maquina'   => $planilla['nombre_maquina'],
            'turno'     => $planilla['nombre_turno'],
            'inicio'    => $planilla['creado_en'],
            'fin'       => $ahora,
            'registros' => $registrosPrevios + $totalTurno,
        ],
        'pdf'       => [
            'nombre'  => nombrePdfPeletizado($planilla['fecha_planilla'], $planilla['nombre_maquina']),
            'maquina' => $planilla['nombre_maquina'],
            'mes'     => date('m-Y', strtotime($planilla['fecha_planilla'])),
            'base64'  => base64_encode($pdfBytes),
        ],
    ]);

    // 'yaExportado' aquí solo puede venir de ESTE mismo clic: no es un turno
    // finalizado antes, es este mismo envío cuya respuesta llegó ambigua.
    $confirmado = !empty($respuesta['ok']) || ($respuesta['error'] ?? '') === 'yaExportado';
    if(!$confirmado
        && turnoYaEnRegistrosPeletizado($planilla['fecha_planilla'], $planilla['nombre_maquina'], $planilla['nombre_turno'])
    ){
        $confirmado = true;
    }

    if(!$confirmado){
        echo json_encode(['ok' => false, 'error' => $respuesta['error'] ?? 'No se pudo enviar el turno a Google.']);
        return;
    }

    $detalleFinal = json_encode([
        'operario1' => $operario1, 'operario2' => $operario2, 'observaciones' => $observaciones, 'filas' => $filas,
    ], JSON_UNESCAPED_UNICODE);
    cerrarPlanillaPeletizado($conexion, $id, $totalTurno, $respuesta['pdf_url'] ?? '', $detalleFinal);

    echo json_encode(['ok' => true, 'yaHecho' => false, 'total' => $totalTurno, 'pdf_url' => $respuesta['pdf_url'] ?? null]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Ocurrió un error al finalizar el turno. Intenta de nuevo.']);
} finally {
    $conexion->query("SELECT RELEASE_LOCK('{$lockName}')");
}
