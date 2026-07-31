<?php
/** @var mysqli $conexion */

// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';

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
$sql_base = "FROM PRODUCCION_EXTRUSION e
LEFT JOIN MAQUINAS m ON e.id_maquina = m.id_maquina
LEFT JOIN TURNOS_EXTRUSION t ON e.id_turno_ext = t.id_turno_ext
LEFT JOIN OPERADORES_EXTRUSION o ON e.id_operador_ext = o.id_operador_ext
LEFT JOIN REFERENCIAS r ON e.id_referencia = r.id_referencia
LEFT JOIN COLORES c ON e.id_color = c.id_color
WHERE 1=1";

// Aplicar filtro de búsqueda por texto
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

// Aplicar filtro por fecha
if(!empty($fecha)){
    $sql_base .= " AND DATE(e.fecha_extrusion) = '$fecha'";
}

// Contar total de registros para la paginación
$total_sql = "SELECT COUNT(*) as total $sql_base";
$total_resultado = mysqli_query($conexion, $total_sql);
$total_fila = mysqli_fetch_assoc($total_resultado);
$total_registros = $total_fila['total'];
$total_paginas = ceil($total_registros / $limite);

// Consulta final con campos y límite de página
$sql = "SELECT e.*, 
            m.nombre_maquina,
            t.nombre_turno_ext, 
            o.nombre_operador_ext, 
            r.nombre_referencia,
            c.nombre_color 
        $sql_base 
        ORDER BY e.id DESC 
        LIMIT $inicio, $limite";

$resultado = mysqli_query($conexion, $sql);
