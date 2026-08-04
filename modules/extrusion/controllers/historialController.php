<?php
/** @var mysqli $conexion */

// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar historialModel.php
require_once dirname(__DIR__) . '/models/historialModel.php';

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
LEFT JOIN TURNOS_EXTRUSION t ON e.id_turno_ext = t.id_turno_ext
LEFT JOIN OPERADORES_EXTRUSION o ON e.id_operador_ext = o.id_operador_ext
LEFT JOIN REFERENCIAS r ON e.id_referencia = r.id_referencia
LEFT JOIN COLORES c ON e.id_color = c.id_color
WHERE 1=1";

// Buscar
if(!empty($busqueda)){
    $sql_base .= " AND (
        m.nombre_maquina LIKE '%$busqueda%' OR
        t.nombre_turno_ext LIKE '%$busqueda%' OR
        o.nombre_operador_ext LIKE '%$busqueda%' OR
        r.nombre_referencia LIKE '%$busqueda%' OR
        c.nombre_color LIKE '%$busqueda%' OR
        e.id LIKE '%$busqueda%' OR
        e.lamina_p LIKE '%$busqueda%' OR
        e.rollos_extrusion LIKE '%$busqueda%' OR
        e.total_extrusion LIKE '%$busqueda%'
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
            t.nombre_turno_ext,
            o.nombre_operador_ext,
            r.nombre_referencia,
            c.nombre_color
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

        'id_turno_ext' => $_POST['id_turno_ext'],

        'id_operador_ext' => $_POST['id_operador_ext'],

        'id_referencia' => $_POST['id_referencia'],

        'id_color' => $_POST['id_color'],

        'lamina_p' => $_POST['lamina_p'],

        'rollos_ext' => $_POST['rollos_extrusion'],

        'total_ext' => $_POST['total_extrusion']
    ];
    actualizarProduccion($conexion,$id,$datos);
    header("Location: ".BASE_URL."/modules/extrusion/views/historial.php");
    exit;
}

// ======================================================
// ELIMINAR REGISTRO
// ======================================================
if(isset($_GET['eliminar'])){
    $id = intval($_GET['eliminar']);
    eliminarProduccion($conexion,$id);
    header("Location: ".BASE_URL."/modules/extrusion/views/historial.php");
    exit;
}