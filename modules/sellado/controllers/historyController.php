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

// Base de la consulta con JOINs
$sql_base = "FROM PRODUCCION_SELLADO s
LEFT JOIN OPERARIOS o ON s.id_operario = o.id_operario
LEFT JOIN MAQUINAS m ON s.id_maquina = m.id_maquina
LEFT JOIN REFERENCIAS r ON s.id_referencia = r.id_referencia
LEFT JOIN COLORES c ON s.id_color = c.id_color
LEFT JOIN TURNOS t ON s.id_turno = t.id_turno
WHERE 1=1";

// Aplicar filtro de búsqueda por texto
if(!empty($busqueda)){
    $sql_base .= " AND (
        o.nombre_operario LIKE '%$busqueda%' OR
        m.nombre_maquina LIKE '%$busqueda%' OR
        r.nombre_referencia LIKE '%$busqueda%' OR
        c.nombre_color LIKE '%$busqueda%' OR
        t.nombre_turno LIKE '%$busqueda%' OR
        s.id LIKE '%$busqueda%' OR
        s.paquetes_x70 LIKE '%$busqueda%' OR
        s.paquetes_x90 LIKE '%$busqueda%' OR
        s.paquetes_x98 LIKE '%$busqueda%' OR
        s.paquetes_total LIKE '%$busqueda%' OR
        s.peso_hora1 LIKE '%$busqueda%' OR
        s.peso_hora2 LIKE '%$busqueda%' OR
        s.peso_hora3 LIKE '%$busqueda%' OR
        s.peso_hora4 LIKE '%$busqueda%' OR
        s.peso_hora5 LIKE '%$busqueda%' OR
        s.promedio_peso LIKE '%$busqueda%'
    )";
}

// Aplicar filtro por fecha
if(!empty($fecha)){
    $sql_base .= " AND DATE(s.fecha_sellado) = '$fecha'";
}

// Contar total de registros para la paginación
$total_sql = "SELECT COUNT(*) as total $sql_base";
$total_resultado = mysqli_query($conexion,$total_sql);
$total_fila = mysqli_fetch_assoc($total_resultado);
$total_registros = $total_fila['total'];
$total_paginas = ceil($total_registros / $limite);

// Consulta final con campos y límite de página
$sql = "SELECT s.*, 
            o.nombre_operario,
            m.nombre_maquina,
            r.nombre_referencia,
            c.nombre_color,
            t.nombre_turno
        $sql_base
        ORDER BY s.id DESC
        LIMIT $inicio, $limite";

$resultado = mysqli_query($conexion,$sql);

if($_SERVER['REQUEST_METHOD']=="POST"){
    // Obtener ID del registro a actualizar
    $id = $_POST['id'];

    // Recopilar datos del formulario
    $datos = [
        'fecha' => $_POST['fecha_sellado'],

        'id_operario' => $_POST['id_operario'],

        'id_maquina' => $_POST['id_maquina'],

        'id_referencia' => $_POST['id_referencia'],

        'id_color' => $_POST['id_color'],

        'id_turno' => $_POST['id_turno'],

        'paq_x70' => $_POST['paquetes_x70'],

        'paq_x90' => $_POST['paquetes_x90'],

        'paq_x98' => $_POST['paquetes_x98'],

        'peso_h1' => $_POST['peso_hora1'],

        'peso_h2' => $_POST['peso_hora2'],

        'peso_h3' => $_POST['peso_hora3'],

        'peso_h4' => $_POST['peso_hora4'],

        'peso_h5' => $_POST['peso_hora5'],

        'obs_sellado' => $_POST['obs_sellado']
    ];

    // Actualizar registro
    actualizarProduccion($conexion, $id, $datos);

    // Redirigir al Historial
    header("Location: " . BASE_URL . "/modules/sellado/views/history.php");
    exit;
}

if(isset($_GET['id'])){
    // Obtener ID del registro a eliminar
    $id = intval($_GET['id'] ?? 0);

    // Eliminar registro
    eliminarProduccion($conexion, $id);

    // Redirigir al Historial
    header("Location: " . BASE_URL . "/modules/sellado/views/history.php");
    exit;
}
