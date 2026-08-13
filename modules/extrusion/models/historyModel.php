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
    return mysqli_query($conexion, "SELECT * FROM MAQUINAS");
}
function obtenerTurnosExtrusion($conexion){
    return mysqli_query($conexion, "SELECT * FROM TURNOS_EXTRUSION");
}
function obtenerOperadoresExtrusion($conexion){
    return mysqli_query($conexion, "SELECT * FROM OPERADORES_EXTRUSION");
}
function obtenerReferenciasExtrusion($conexion){
    return mysqli_query($conexion, "SELECT * FROM REFERENCIAS");
}
function obtenerColoresExtrusion($conexion){
    return mysqli_query($conexion, "SELECT * FROM COLORES");
}

/* =================================================
   ACTUALIZAR
================================================= */
// Actualizar registro de producción por ID
function actualizarProduccion($conexion, $id, $datos){
    $sql = "UPDATE PRODUCCION_EXTRUSION SET
            fecha_extrusion = '{$datos['fecha']}',
            id_maquina = '{$datos['id_maquina']}',
            id_turno_ext = '{$datos['id_turno_ext']}',
            id_operador_ext = '{$datos['id_operador_ext']}',
            id_referencia = '{$datos['id_referencia']}',
            id_color = '{$datos['id_color']}',
            lamina_p = '{$datos['lamina_p']}',
            rollos_extrusion = '{$datos['rollos_ext']}',
            total_extrusion = '{$datos['total_ext']}'
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
