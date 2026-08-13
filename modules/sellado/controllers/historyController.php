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
