<?php
/** @var mysqli $conexion */

// Datos del panel de Inicio (AJAX, JSON). Solo administradores.
//   ?accion=meta                                     -> módulos, dimensiones y medidas
//   ?accion=general                                  -> tarjetas de arriba (mes actual + estado)
//   ?accion=catalogos                                -> estado de los catálogos
//   ?accion=resumen&modulo=&desde=&hasta=            -> resumen de un módulo en un rango
//   ?accion=estado&modulo=                           -> estado (importación, planillas...) de un módulo
//   ?accion=pendientes&modulo=[&forzar=1]            -> registros del Sheet por importar
//   ?accion=opciones&modulo=&dim=                    -> valores para filtrar
//   ?accion=datos&modulo=&dims[]=&desde=&hasta=&f[dim][]=valor -> filas agrupadas

$soloAdmin = true;
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__) . '/models/homeModel.php';

header('Content-Type: application/json');
set_time_limit(90);

$modulos = homeModulos();
$accion  = $_GET['accion'] ?? '';
$desde   = homeFecha($_GET['desde'] ?? '');
$hasta   = homeFecha($_GET['hasta'] ?? '');

if ($accion === 'meta') {
    echo json_encode(['ok' => true, 'modulos' => homeMeta()]);
    exit;
}
if ($accion === 'general') {
    echo json_encode(['ok' => true] + homeGeneral($conexion));
    exit;
}
if ($accion === 'catalogos') {
    echo json_encode(['ok' => true] + homeCatalogos($conexion));
    exit;
}

$clave = $_GET['modulo'] ?? '';
if (!isset($modulos[$clave])) {
    echo json_encode(['ok' => false, 'error' => 'Módulo inválido.']);
    exit;
}
$mod = $modulos[$clave];

if ($accion === 'resumen') {
    echo json_encode(['ok' => true, 'resumen' => homeResumenModulo($conexion, $clave, $desde, $hasta)]);
    exit;
}
if ($accion === 'estado') {
    echo json_encode(['ok' => true, 'estado' => homeEstadoModulo($conexion, $clave)]);
    exit;
}
if ($accion === 'pendientes') {
    echo json_encode(['ok' => true] + homePendientes($conexion, $clave, !empty($_GET['forzar'])));
    exit;
}
if ($accion === 'opciones') {
    $dim = $_GET['dim'] ?? '';
    if (!isset($mod['dims'][$dim])) {
        echo json_encode(['ok' => false, 'error' => 'Dimensión inválida.']);
        exit;
    }
    echo json_encode(['ok' => true, 'valores' => homeOpciones($conexion, $clave, $dim)]);
    exit;
}

if ($accion === 'datos') {
    // Solo dimensiones válidas, sin repetir, máximo 4
    $dims = [];
    foreach ((array) ($_GET['dims'] ?? []) as $d) {
        if (isset($mod['dims'][$d]) && !in_array($d, $dims, true)) {
            $dims[] = $d;
        }
    }
    $dims = array_slice($dims, 0, 4);

    $filtros = [];
    foreach ((array) ($_GET['f'] ?? []) as $d => $valores) {
        if (isset($mod['dims'][$d]) && is_array($valores)) {
            $filtros[$d] = array_slice(array_map('strval', $valores), 0, 200);
        }
    }

    echo json_encode(['ok' => true, 'dims' => $dims, 'filas' => homeDatos($conexion, $clave, $dims, $desde, $hasta, $filtros)]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Acción inválida.']);
