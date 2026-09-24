<?php
/* =================================================
   CONSULTAS
================================================= */
// Obtener registro de producción por ID
function obtenerRegistroExtrusionPorId($conexion, $id){
    $sql = "SELECT * FROM PRODUCCION_EXTRUSION WHERE id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

// Catálogos para selectores del formulario
function obtenerMaquinasExtrusion($conexion){
    return mysqli_query($conexion, "SELECT * FROM MAQUINAS ORDER BY CAST(REGEXP_SUBSTR(nombre_maquina, '[0-9]+') AS UNSIGNED)");
}
function obtenerTurnosExtrusion($conexion){
    return mysqli_query($conexion, "SELECT * FROM TURNOS");
}
function obtenerOperadoresExtrusion($conexion){
    return mysqli_query($conexion, "SELECT * FROM OPERADORES");
}
function obtenerReferenciasExtrusion($conexion){
    return mysqli_query($conexion, "SELECT * FROM REFERENCIAS");
}
function obtenerColoresExtrusion($conexion){
    return mysqli_query($conexion, "SELECT * FROM COLORES");
}
function obtenerLaminaPExtrusion($conexion){
    return mysqli_query($conexion, "SELECT * FROM LAMINA_P");
}

/* =================================================
   ACTUALIZAR
================================================= */
// Actualizar registro de producción por ID
function actualizarProduccion($conexion, $id, $datos){
    $sql = "UPDATE PRODUCCION_EXTRUSION SET
            fecha_extrusion = '{$datos['fecha']}',
            id_maquina = '{$datos['id_maquina']}',
            id_turno = '{$datos['id_turno']}',
            id_operador = '{$datos['id_operador']}',
            id_referencia = '{$datos['id_referencia']}',
            id_color = '{$datos['id_color']}',
            id_lamina_p = '{$datos['id_lamina_p']}',
            rollos = '{$datos['rollos']}',
            peso_total = '{$datos['peso_total']}'
            WHERE id = $id";
    mysqli_query($conexion, $sql);
}

/* =================================================
   ELIMINAR
================================================= */
// Eliminar registro de producción por ID
function eliminarProduccion($conexion, $id){
    $sql = "DELETE FROM PRODUCCION_EXTRUSION 
            WHERE id = $id";
    mysqli_query($conexion, $sql);
}
