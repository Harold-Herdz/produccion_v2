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
$cfg   = obtenerConfigCatalogo($clave);

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

    // Redirigir de vuelta a la vista con el catálogo seleccionado
    header('Location: ' . $rutaVista . '?cat=' . urlencode($clave));
    exit;
}

/* =====================================================
   LISTAR REGISTROS (GET)
===================================================== */
$busqueda  = trim($_GET['buscar'] ?? '');
$registros = listarRegistros($conexion, $cfg, $busqueda);
