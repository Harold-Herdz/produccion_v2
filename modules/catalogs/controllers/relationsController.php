<?php
/** @var mysqli $conexion */

/**
 * =====================================================
 *  CONTROLADOR DE RELACIONES
 * =====================================================
 *  Administra las relaciones entre catálogos:
 *    - Máquina × Área
 *    - Referencia × Máquina (por área, y "Especiales")
 * Cambios por AJAX (JSON)
 */

// Restringir acceso solo a administradores
$soloAdmin = true;

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/relationsModel.php';

/* =====================================================
   ACCIONES (POST, AJAX)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'toggle_maquina_area') {
        alternarMaquinaArea($conexion, $_POST['id_maquina'] ?? 0, $_POST['id_area'] ?? 0);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($accion === 'toggle_maquina_referencia') {
        alternarMaquinaReferencia($conexion, $_POST['id_maquina'] ?? 0, $_POST['id_area'] ?? 0, $_POST['id_referencia'] ?? 0);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($accion === 'toggle_maquina_esp') {
        alternarUsaReferenciasEsp($conexion, $_POST['id_maquina'] ?? 0, $_POST['id_area'] ?? 0);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($accion === 'toggle_operario_area') {
        alternarOperarioArea($conexion, $_POST['id_operario'] ?? 0, $_POST['id_area'] ?? 0);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($accion === 'toggle_color_area') {
        alternarColorArea($conexion, $_POST['id_color'] ?? 0, $_POST['id_area'] ?? 0);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($accion === 'exportar') {
        $tipos = array_values(array_intersect((array) ($_POST['tipos'] ?? []), ['areas', 'referencias', 'operarios', 'colores']));
        $t = exportarRelaciones($conexion, $tipos);
        $resultados = [];
        if (in_array('areas', $tipos, true)) {
            $resultados[] = ['etiqueta' => 'Máquinas × Áreas', 'detalle' => "{$t['areas']} exportadas", 'error' => false];
        }
        if (in_array('referencias', $tipos, true)) {
            $resultados[] = ['etiqueta' => 'Referencias × Máquinas', 'detalle' => "{$t['referencias']} exportadas", 'error' => false];
        }
        if (in_array('operarios', $tipos, true)) {
            $resultados[] = ['etiqueta' => 'Operarios × Áreas', 'detalle' => "{$t['operarios']} exportadas", 'error' => false];
        }
        if (in_array('colores', $tipos, true)) {
            $resultados[] = ['etiqueta' => 'Colores × Áreas', 'detalle' => "{$t['colores']} exportadas", 'error' => false];
        }
        if (!$resultados) {
            $resultados[] = ['etiqueta' => 'Relaciones', 'detalle' => 'No seleccionaste nada para exportar.', 'error' => true];
        }
        echo json_encode(['tipo' => 'exportar', 'resultados' => $resultados]);
        exit;
    }
    if ($accion === 'importar') {
        $tipos = array_values(array_intersect((array) ($_POST['tipos'] ?? []), ['areas', 'referencias', 'operarios', 'colores']));
        if (!$tipos) {
            echo json_encode(['tipo' => 'importar', 'resultados' => [
                ['etiqueta' => 'Relaciones', 'detalle' => 'No seleccionaste nada para importar.', 'error' => true],
            ]]);
            exit;
        }
        $r = importarRelaciones($conexion, $tipos);
        $detalle = "{$r['agregados']} agregadas, {$r['existentes']} ya existían" . ($r['omitidos'] ? ", {$r['omitidos']} omitidas (ya no existe la máquina, área, operario o referencia)" : '');
        echo json_encode(['tipo' => 'importar', 'resultados' => [
            $r['error']
                ? ['etiqueta' => 'Relaciones', 'detalle' => $r['error'], 'error' => true]
                : ['etiqueta' => 'Relaciones', 'detalle' => $detalle, 'error' => false],
        ]]);
        exit;
    }

    echo json_encode(['ok' => false]);
    exit;
}

/* =====================================================
   RELACIÓN ACTIVA (GET): areas | referencias | operarios | colores
===================================================== */
$relesValidos = ['areas', 'referencias', 'operarios', 'colores'];
$rel = in_array($_GET['rel'] ?? '', $relesValidos, true) ? $_GET['rel'] : 'areas';
// Peletizado no maneja referencias: no aparece en ese selector (sí en Operarios × Áreas)
$areas = array_values(array_filter(areasOrdenadas($conexion), fn($a) => $a['nombre_area'] !== 'peletizado'));
$idArea = (int) ($_GET['area'] ?? 0);
if ($idArea && !array_filter($areas, fn($a) => (int) $a['id_area'] === $idArea)) {
    $idArea = 0;
}
