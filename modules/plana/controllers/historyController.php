<?php
/** @var mysqli $conexion */

// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar produccionModel.php
require_once dirname(__DIR__) . '/models/historyModel.php';

// Obtener y sanear filtros
$busqueda = $_GET['buscar'] ?? '';
$fecha = $_GET['fecha'] ?? '';
$busqueda = mysqli_real_escape_string($conexion, $busqueda);
$fecha = mysqli_real_escape_string($conexion, $fecha);

// Configurar paginación
$limite = 10;
$pagina = $_GET['pagina'] ?? 1;
$inicio = ($pagina - 1) * $limite;

// Consulta base con JOINs
$sql_base = "FROM PRODUCCION_PLANA p
LEFT JOIN MAQUINAS m ON p.id_maquina = m.id_maquina
LEFT JOIN OPERARIOS o ON p.id_operario = o.id_operario
LEFT JOIN REFERENCIAS_ESP r ON p.id_referencia_esp = r.id_referencia_esp
WHERE 1=1";

// Filtro de búsqueda
if(!empty($busqueda)){
    $sql_base .= " AND (
        r.nombre_referencia_esp LIKE '%$busqueda%' OR
        o.nombre_operario LIKE '%$busqueda%' OR
        m.nombre_maquina LIKE '%$busqueda%' OR
        p.id LIKE '%$busqueda%' OR
        p.peso_rollo LIKE '%$busqueda%' OR
        p.bultos LIKE '%$busqueda%' OR
        p.peso_retal LIKE '%$busqueda%' OR
        p.peso_total LIKE '%$busqueda%'
    )";
}

// Aplicar filtro por fecha
if(!empty($fecha)){
    $sql_base .= " AND DATE(p.fecha_plana) = '$fecha'";
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
            o.nombre_operario,
            r.nombre_referencia_esp
        $sql_base
        ORDER BY p.id DESC
        LIMIT $inicio, $limite";

$resultado = mysqli_query($conexion, $sql);

if($_SERVER['REQUEST_METHOD']=="POST"){
    // ID a actualizar
    $id = $_POST['id'];

    // Recopilar datos del formulario
    $datos = [
        'fecha' => $_POST['fecha_plana'],

        'id_maquina' => $_POST['id_maquina'],

        'id_operario' => $_POST['id_operario'],

        'id_referencia_esp' => $_POST['id_referencia_esp'],

        'peso_rollo' => $_POST['peso_rollo'],

        'bultos' => $_POST['bultos'],

        'peso_retal' => $_POST['peso_retal'],

        'peso_total' => $_POST['peso_total']
    ];

    // Actualizar registro
    actualizarProduccion($conexion, $id, $datos);

    // Redirigir al Historial
    header("Location: " . BASE_URL . "/modules/plana/views/history.php");
    exit;
}

if(isset($_GET['id'])){
    // ID a eliminar
    $id = intval($_GET['id'] ?? 0);

    // Eliminar registro
    eliminarProduccion($conexion, $id);

    // Redirigir al Historial
    header("Location: " . BASE_URL . "/modules/plana/views/history.php");
    exit;
}
