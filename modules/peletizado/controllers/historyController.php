<?php
/** @var mysqli $conexion */

// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar historyModel.php
require_once dirname(__DIR__) . '/models/historyModel.php';

// Obtener y sanear filtros
$busqueda = $_GET['buscar'] ?? '';
$fecha = $_GET['fecha'] ?? '';
$busqueda = mysqli_real_escape_string($conexion, $busqueda);
$fecha = mysqli_real_escape_string($conexion, $fecha);

// Configurar paginación
$limite = 10;
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$inicio = ($pagina - 1) * $limite;

// Consulta base con JOINs (operario 2 usa el mismo catálogo de operarios)
$sql_base = "FROM PRODUCCION_PELETIZADO p
LEFT JOIN MAQUINAS m ON p.id_maquina = m.id_maquina
LEFT JOIN TURNOS t ON p.id_turno = t.id_turno
LEFT JOIN OPERARIOS o1 ON p.id_operario = o1.id_operario
LEFT JOIN OPERARIOS o2 ON p.id_operario2 = o2.id_operario
LEFT JOIN COLORES c ON p.id_color = c.id_color
WHERE 1=1";

// Filtro de búsqueda
if(!empty($busqueda)){
    $sql_base .= " AND (
        m.nombre_maquina LIKE '%$busqueda%' OR
        t.nombre_turno LIKE '%$busqueda%' OR
        o1.nombre_operario LIKE '%$busqueda%' OR
        o2.nombre_operario LIKE '%$busqueda%' OR
        c.nombre_color LIKE '%$busqueda%' OR
        p.id LIKE '%$busqueda%' OR
        p.obs_peletizado LIKE '%$busqueda%' OR
        p.total LIKE '%$busqueda%'
    )";
}

// Aplicar filtro por fecha
if(!empty($fecha)){
    $sql_base .= " AND DATE(p.fecha_peletizado) = '$fecha'";
}

// Total para paginación
$total_sql = "SELECT COUNT(*) as total $sql_base";
$total_resultado = mysqli_query($conexion, $total_sql);
$total_fila = mysqli_fetch_assoc($total_resultado);
$total_registros = $total_fila['total'];
$total_paginas = ceil($total_registros / $limite);

// Consulta final paginada
$sql = "SELECT p.*,
            m.nombre_maquina,
            t.nombre_turno,
            o1.nombre_operario AS nombre_operario,
            o2.nombre_operario AS nombre_operario2,
            c.nombre_color
        $sql_base
        ORDER BY p.id DESC
        LIMIT $inicio, $limite";

$resultado = mysqli_query($conexion, $sql);

if($_SERVER['REQUEST_METHOD']=="POST"){
    // ID a actualizar
    $id = (int) $_POST['id'];

    // Recopilar datos del formulario
    $idOperario2 = $_POST['id_operario2'] ?? '';
    $idColor = $_POST['id_color'] ?? '';
    $datos = [
        'fecha' => $_POST['fecha_peletizado'],
        'id_maquina' => (int) $_POST['id_maquina'],
        'id_turno' => (int) $_POST['id_turno'],
        'id_operario' => (int) $_POST['id_operario'],
        'id_operario2' => ($idOperario2 === '') ? null : (int) $idOperario2,
        'id_color' => ($idColor === '') ? null : (int) $idColor,
        'alta_retal' => (int) ($_POST['alta_retal'] ?? 0),
        'baja' => (int) ($_POST['baja'] ?? 0),
        'refiltrado' => (int) ($_POST['refiltrado'] ?? 0),
        'soplado' => (int) ($_POST['soplado'] ?? 0),
        'torta' => (int) ($_POST['torta'] ?? 0),
        'limpieza' => (int) ($_POST['limpieza'] ?? 0),
        'total' => (int) ($_POST['total'] ?? 0),
        'obs_peletizado' => $_POST['obs_peletizado'] ?? '',
    ];

    // Actualizar registro
    actualizarProduccionPeletizado($conexion, $id, $datos);

    // Redirigir al Historial
    header("Location: " . BASE_URL . "/modules/peletizado/views/history.php");
    exit;
}

if(isset($_GET['id'])){
    // ID a eliminar
    $id = intval($_GET['id'] ?? 0);

    // Eliminar registro
    eliminarProduccionPeletizado($conexion, $id);

    // Redirigir al Historial
    header("Location: " . BASE_URL . "/modules/peletizado/views/history.php");
    exit;
}
