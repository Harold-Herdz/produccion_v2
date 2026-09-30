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

    if ($accion === 'exportar') {
        $t = exportarRelaciones($conexion);
        echo json_encode(['tipo' => 'exportar', 'resultados' => [
            ['etiqueta' => 'Máquinas × Áreas',       'detalle' => "{$t['areas']} exportadas", 'error' => false],
            ['etiqueta' => 'Referencias × Máquinas', 'detalle' => "{$t['referencias']} exportadas", 'error' => false],
        ]]);
        exit;
    }
    if ($accion === 'importar') {
        $r = importarRelaciones($conexion);
        $detalle = "{$r['agregados']} agregadas, {$r['existentes']} ya existían" . ($r['omitidos'] ? ", {$r['omitidos']} omitidas (ya no existe la máquina, área o referencia)" : '');
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
   RELACIÓN ACTIVA (GET): areas | referencias
===================================================== */
$rel   = ($_GET['rel'] ?? 'areas') === 'referencias' ? 'referencias' : 'areas';
// Peletizado no maneja referencias: no aparece en este selector
$areas = array_values(array_filter(areasOrdenadas($conexion), fn($a) => $a['nombre_area'] !== 'peletizado'));
$idArea = (int) ($_GET['area'] ?? 0);
if ($idArea && !array_filter($areas, fn($a) => (int) $a['id_area'] === $idArea)) {
    $idArea = 0;
}
