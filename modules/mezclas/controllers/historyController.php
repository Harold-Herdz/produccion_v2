<?php
/** @var mysqli $conexion */

require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
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

// Consulta base con JOINs
$sql_base = "FROM PRODUCCION_MEZCLA p
LEFT JOIN OPERARIOS o ON p.id_operario = o.id_operario
LEFT JOIN MAQUINAS m ON p.id_maquina = m.id_maquina
LEFT JOIN REFERENCIAS ref ON p.id_referencia = ref.id_referencia
WHERE 1=1";

// Filtro de búsqueda
if(!empty($busqueda)){
    $sql_base .= " AND (
        ref.nombre_referencia LIKE '%$busqueda%' OR
        o.nombre_operario LIKE '%$busqueda%' OR
        m.nombre_maquina LIKE '%$busqueda%' OR
        p.id_sheet LIKE '%$busqueda%' OR
        p.observaciones LIKE '%$busqueda%'
    )";
}

// Aplicar filtro por fecha
if(!empty($fecha)){
    $sql_base .= " AND DATE(p.fecha_mezcla) = '$fecha'";
}

// Total para paginación
$total_sql = "SELECT COUNT(*) as total $sql_base";
$total_resultado = mysqli_query($conexion, $total_sql);
$total_fila = mysqli_fetch_assoc($total_resultado);
$total_registros = $total_fila['total'];

$total_paginas = ceil($total_registros / $limite);

// Consulta final paginada
$sql = "SELECT p.*,
            o.nombre_operario,
            m.nombre_maquina,
            ref.nombre_referencia
        $sql_base
        ORDER BY p.id DESC
        LIMIT $inicio, $limite";

$resultado = mysqli_query($conexion, $sql);

if($_SERVER['REQUEST_METHOD']=="POST"){
    // ID a actualizar
    $id = $_POST['id'];

    // Recopilar datos del formulario
    $datos = [
        'fecha' => $_POST['fecha_mezcla'],
        'id_operario' => $_POST['id_operario'],
        'id_maquina' => $_POST['id_maquina'],
        'id_referencia' => $_POST['id_referencia'],
        'observaciones' => $_POST['observaciones'],
    ];
    foreach(array_merge(array_keys(columnasTabla1MezclaHistorial()), array_keys(columnasTabla2MezclaHistorial())) as $col){
        $datos[$col] = $_POST[$col] ?? '';
    }

    // Actualizar registro
    actualizarProduccion($conexion, $id, $datos);

    // Redirigir al Historial
    header("Location: " . BASE_URL . "/modules/mezclas/views/history.php");
    exit;
}

if(isset($_GET['id'])){
    // ID a eliminar
    $id = intval($_GET['id'] ?? 0);

    // Eliminar registro
    eliminarProduccion($conexion, $id);

    // Redirigir al Historial
    header("Location: " . BASE_URL . "/modules/mezclas/views/history.php");
    exit;
}
