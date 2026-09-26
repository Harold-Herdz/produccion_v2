<?php
/** @var mysqli $conexion */

/**
 * =====================================================
 *  CONTROLADOR DE CATÁLOGOS
 * =====================================================
 * Administra las tablas maestras
 *
 * Redirige tras cada POST
 */

// Restringir acceso solo a administradores
$soloAdmin = true;

// Valida sesión y rol admin
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar catalogsModel.php
require_once dirname(__DIR__) . '/models/catalogsModel.php';

// Ruta de la vista
$rutaVista = BASE_URL . '/modules/catalogs/views/catalogs.php';

/* =====================================================
   DETERMINAR EL CATÁLOGO ACTIVO
   -----------------------------------------------------
   La clave llega por POST (al crear/cambiar estado) o por
   GET (al navegar). Si no es válida, se usa "operarios".
===================================================== */
$clave = $_POST['cat'] ?? $_GET['cat'] ?? 'operarios';

// Relaciones: relationsController.php
$cfg = obtenerConfigCatalogo($clave);

if ($cfg === null) {
    $clave = 'operarios';
    $cfg   = obtenerConfigCatalogo($clave);
}

/* =====================================================
   PROCESAR ACCIONES (POST)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';

    // Crear registro
    if ($accion === 'crear') {
        crearRegistro($conexion, $cfg, $_POST);
    }

    // Activar / inhabilitar
    if ($accion === 'estado') {
        $idRegistro = $_POST['id'] ?? 0;
        cambiarEstadoRegistro($conexion, $cfg, $idRegistro);
    }

    // Marcar supervisor
    if ($accion === 'toggle_supervisor' && $clave === 'operarios') {
        alternarSupervisorOperario($conexion, $_POST['id'] ?? 0);
    }

    // Exportar / importar (AJAX)
    if ($accion === 'exportar' || $accion === 'importar') {
        $seleccionados = $_POST['catalogos'] ?? [];
        $resultados = [];
        foreach (catalogosDisponibles() as $key => $cfgCat) {
            if (!in_array($key, $seleccionados, true)) {
                continue;
            }
            if ($accion === 'exportar') {
                $total = exportarCatalogo($conexion, $cfgCat);
                $resultados[] = ['etiqueta' => $cfgCat['etiqueta'], 'detalle' => "{$total} exportados", 'error' => false];
            } else {
                $r = importarCatalogo($conexion, $cfgCat);
                $resultados[] = $r['error']
                    ? ['etiqueta' => $cfgCat['etiqueta'], 'detalle' => $r['error'], 'error' => true]
                    : ['etiqueta' => $cfgCat['etiqueta'], 'detalle' => "{$r['agregados']} agregados, {$r['existentes']} ya existían", 'error' => false];
            }
        }

        // Usuarios: exportar/importar
        if (in_array('usuarios', $seleccionados, true)) {
            if ($accion === 'exportar') {
                $total = exportarUsuarios($conexion);
                $resultados[] = ['etiqueta' => 'Usuarios', 'detalle' => "{$total} exportados", 'error' => false];
            } else {
                $r = importarUsuarios($conexion);
                $resultados[] = $r['error']
                    ? ['etiqueta' => 'Usuarios', 'detalle' => $r['error'], 'error' => true]
                    : ['etiqueta' => 'Usuarios', 'detalle' => "{$r['agregados']} agregados, {$r['existentes']} ya existían", 'error' => false];
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['tipo' => $accion, 'resultados' => $resultados]);
        exit;
    }

    // Volver a la vista
    header('Location: ' . $rutaVista . '?cat=' . urlencode($clave));
    exit;
}

/* =====================================================
   LISTAR REGISTROS (GET)
===================================================== */
$busqueda  = trim($_GET['buscar'] ?? '');
$registros = listarRegistros($conexion, $cfg, $busqueda);
