<?php
/* =================================================
   CONSULTAS
================================================= */
// Registro por ID
function obtenerRegistroPlanaPorId($conexion, $id){
    $sql = "SELECT * FROM PRODUCCION_PLANA WHERE id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

// Catálogos para selectores del formulario
function obtenerMaquinasPlana($conexion){
    return mysqli_query($conexion, "SELECT * FROM MAQUINAS ORDER BY CAST(REGEXP_SUBSTR(nombre_maquina, '[0-9]+') AS UNSIGNED)");
}
function obtenerOperariosPlana($conexion){
    return mysqli_query($conexion, "SELECT * FROM OPERARIOS");
}
function obtenerReferenciasPlana($conexion){
    return mysqli_query($conexion, "SELECT * FROM REFERENCIAS_ESP");
}

/* =================================================
   ACTUALIZAR
================================================= */
// Actualizar registro
function actualizarProduccion($conexion, $id, $datos){
    $sql = "UPDATE PRODUCCION_PLANA SET
            fecha_plana = '{$datos['fecha']}',
            id_maquina = '{$datos['id_maquina']}',
            id_operario = '{$datos['id_operario']}',
            id_referencia_esp = '{$datos['id_referencia_esp']}',
            peso_rollo = '{$datos['peso_rollo']}',
            peso_retal = '{$datos['peso_retal']}',
            bultos = '{$datos['bultos']}',
            peso_total = '{$datos['peso_total']}'
            WHERE id = $id";
    mysqli_query($conexion, $sql);
}

/* =================================================
   ELIMINAR
================================================= */
// Eliminar registro
function eliminarProduccion($conexion, $id){
    $sql = "DELETE FROM PRODUCCION_PLANA 
            WHERE id = $id";
    mysqli_query($conexion, $sql);
}
