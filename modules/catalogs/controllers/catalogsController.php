<?php
/** @var mysqli $conexion */

/**
 * =====================================================
 *  CONTROLADOR DE CATÁLOGOS
 * =====================================================
 *  Coordina la administración de las tablas maestras:
 *    - GET  : listar los registros del catálogo seleccionado
 *    - POST : crear un registro           (accion = "crear")
 *    - POST : activar / inhabilitar       (accion = "estado")
 *
 *  Tras cada POST se redirige a la vista (patrón PRG)
 *  para evitar el reenvío del formulario al recargar.
 */

// Restringir acceso solo a administradores
$soloAdmin = true;

// Importar authMiddleware.php  (valida sesión y rol admin)
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar catalogsModel.php
require_once dirname(__DIR__) . '/models/catalogsModel.php';

// Ruta de la vista para las redirecciones
$rutaVista = BASE_URL . '/modules/catalogs/views/catalogs.php';

/* =====================================================
   DETERMINAR EL CATÁLOGO ACTIVO
   -----------------------------------------------------
   La clave llega por POST (al crear/cambiar estado) o por
   GET (al navegar). Si no es válida, se usa "operarios".
===================================================== */
$clave = $_POST['cat'] ?? $_GET['cat'] ?? 'operarios';

// (Las relaciones máquina↔área / máquina↔referencia se administran en relationsController.php)
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

    // Crear un nuevo registro dentro del catálogo actual
    if ($accion === 'crear') {
        crearRegistro($conexion, $cfg, $_POST);
    }

    // Activar / inhabilitar un registro existente
    if ($accion === 'estado') {
        $idRegistro = $_POST['id'] ?? 0;
        cambiarEstadoRegistro($conexion, $cfg, $idRegistro);
    }

    // Marcar / desmarcar un operario como supervisor
    if ($accion === 'toggle_supervisor' && $clave === 'operarios') {
        alternarSupervisorOperario($conexion, $_POST['id'] ?? 0);
    }

    // Exportar / importar varios catálogos (AJAX; responde JSON, sin recargar la página)
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

        // Usuarios: exporta/importa con contraseña y rol (no es un catálogo genérico)
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

    // Redirigir de vuelta a la vista con el catálogo seleccionado
    header('Location: ' . $rutaVista . '?cat=' . urlencode($clave));
    exit;
}

/* =====================================================
   LISTAR REGISTROS (GET)
===================================================== */
$busqueda  = trim($_GET['buscar'] ?? '');
$registros = listarRegistros($conexion, $cfg, $busqueda);
