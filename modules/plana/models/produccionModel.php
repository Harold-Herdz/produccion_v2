<?php
/* =================================================
   CONSULTAS
================================================= */
// Obtener registro de producción por ID
function obtenerRegistroPlanaPorId($conexion, $id){
    $sql = "SELECT * FROM PRODUCCION_PLANA WHERE id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

// Catálogos para selectores del formulario
function obtenerMaquinasPlana($conexion){
    return mysqli_query($conexion, "SELECT * FROM MAQUINAS");
}
function obtenerTurnosPlana($conexion){
    return mysqli_query($conexion, "SELECT * FROM TURNOS");
}
function obtenerOperariosPlana($conexion){
    return mysqli_query($conexion, "SELECT * FROM OPERARIOS");
}
function obtenerReferenciasPlana($conexion){
    return mysqli_query($conexion, "SELECT * FROM REFERENCIAS");
}

/* =================================================
   ACTUALIZAR
================================================= */
// Actualizar registro de producción por ID
function actualizarProduccion($conexion, $id, $datos){
    $sql = "UPDATE PRODUCCION_PLANA SET
            fecha_plana = '{$datos['fecha']}',
            id_maquina = '{$datos['id_maquina']}',
            id_turno = '{$datos['id_turno']}',
            id_operario = '{$datos['id_operario']}',
            id_referencia = '{$datos['id_referencia']}',
            peso_plana = '{$datos['peso']}',
            bultos_plana = '{$datos['bultos']}',
            retal_plana = '{$datos['retal']}',
            total_plana = '{$datos['total']}'
            WHERE id = $id";
    mysqli_query($conexion, $sql);
}

/* =================================================
   ELIMINAR
================================================= */
// Eliminar registro de producción por ID
function eliminarProduccion($conexion, $id){
    $sql = "DELETE FROM PRODUCCION_PLANA 
            WHERE id = $id";
    mysqli_query($conexion, $sql);
}
