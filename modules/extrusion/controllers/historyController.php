<?php
/** @var mysqli $conexion */

// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar historyModel.php
require_once dirname(__DIR__) . '/models/historyModel.php';

// ======================================================
// LISTAR PRODUCCIÓN
// ======================================================
// Obtener y sanear filtros
$busqueda = $_GET['buscar'] ?? '';
$fecha = $_GET['fecha'] ?? '';
$busqueda = mysqli_real_escape_string($conexion, $busqueda);
$fecha = mysqli_real_escape_string($conexion, $fecha);

// Configurar paginación
$limite = 10;
$pagina = $_GET['pagina'] ?? 1;
$inicio = ($pagina - 1) * $limite;

// Base de consulta
$sql_base = "FROM PRODUCCION_EXTRUSION e
LEFT JOIN MAQUINAS m ON e.id_maquina = m.id_maquina
LEFT JOIN TURNOS t ON e.id_turno = t.id_turno
LEFT JOIN OPERADORES o ON e.id_operador = o.id_operador
LEFT JOIN REFERENCIAS r ON e.id_referencia = r.id_referencia
LEFT JOIN COLORES c ON e.id_color = c.id_color
LEFT JOIN LAMINA_P l ON e.id_lamina_p = l.id_lamina_p
WHERE 1=1";

// Buscar
if(!empty($busqueda)){
    $sql_base .= " AND (
        m.nombre_maquina LIKE '%$busqueda%' OR
        t.nombre_turno LIKE '%$busqueda%' OR
        o.nombre_operador LIKE '%$busqueda%' OR
        r.nombre_referencia LIKE '%$busqueda%' OR
        c.nombre_color LIKE '%$busqueda%' OR
        e.id LIKE '%$busqueda%' OR
        l.nombre_lamina_p LIKE '%$busqueda%' OR
        e.rollos LIKE '%$busqueda%' OR
        e.peso_total LIKE '%$busqueda%'
    )";
}

// Fecha
if(!empty($fecha)){
    $sql_base .= " AND DATE(e.fecha_extrusion) = '$fecha'";
}
// Total registros
$total_sql = "SELECT COUNT(*) as total $sql_base";
$total_resultado = mysqli_query($conexion,$total_sql);
$total_fila = mysqli_fetch_assoc($total_resultado);
$total_registros = $total_fila['total'];
$total_paginas = ceil($total_registros/$limite);

// Consulta principal
$sql = "SELECT
            e.*,
            m.nombre_maquina,
            t.nombre_turno,
            o.nombre_operador,
            r.nombre_referencia,
            c.nombre_color,
            l.nombre_lamina_p
        $sql_base
        ORDER BY e.id DESC
        LIMIT $inicio,$limite";
$resultado = mysqli_query($conexion,$sql);

// ======================================================
// ACTUALIZAR REGISTRO
// ======================================================
if($_SERVER['REQUEST_METHOD']=="POST"){
    $id = $_POST['id'];
    $datos = [
        'fecha' => $_POST['fecha_extrusion'],

        'id_maquina' => $_POST['id_maquina'],

        'id_turno' => $_POST['id_turno'],

        'id_operador' => $_POST['id_operador'],

        'id_referencia' => $_POST['id_referencia'],

        'id_color' => $_POST['id_color'],

        'id_lamina_p' => $_POST['id_lamina_p'],

        'rollos' => $_POST['rollos'],

        'peso_total' => $_POST['peso_total']
    ];
    actualizarProduccion($conexion,$id,$datos);
    header("Location: ".BASE_URL."/modules/extrusion/views/history.php");
    exit;
}

// ======================================================
// ELIMINAR REGISTRO
// ======================================================
if(isset($_GET['eliminar'])){
    $id = intval($_GET['eliminar']);
    eliminarProduccion($conexion,$id);
    header("Location: ".BASE_URL."/modules/extrusion/views/history.php");
    exit;
}