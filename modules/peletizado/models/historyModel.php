<?php
/* =================================================
   CONSULTAS
================================================= */
// Registro por ID
function obtenerRegistroPeletizadoPorId($conexion, $id){
    $stmt = $conexion->prepare("SELECT * FROM PRODUCCION_PELETIZADO WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Catálogos para selectores del formulario
function obtenerMaquinasPeletizado($conexion){
    return mysqli_query($conexion, "SELECT * FROM MAQUINAS ORDER BY CAST(REGEXP_SUBSTR(nombre_maquina, '[0-9]+') AS UNSIGNED)");
}
function obtenerTurnosPeletizado($conexion){
    return mysqli_query($conexion, "SELECT * FROM TURNOS");
}
function obtenerOperariosPeletizado($conexion){
    return mysqli_query($conexion, "SELECT * FROM OPERARIOS");
}
function obtenerColoresPeletizado($conexion){
    return mysqli_query($conexion, "SELECT * FROM COLORES");
}

/* =================================================
   ACTUALIZAR
================================================= */
// Actualizar registro (operario 2 y color son opcionales)
function actualizarProduccionPeletizado($conexion, $id, $datos){
    $stmt = $conexion->prepare("UPDATE PRODUCCION_PELETIZADO SET
            fecha_peletizado = ?,
            id_maquina = ?,
            id_turno = ?,
            id_operario = ?,
            id_operario2 = ?,
            id_color = ?,
            alta_retal = ?,
            baja = ?,
            refiltrado = ?,
            soplado = ?,
            torta = ?,
            limpieza = ?,
            total = ?,
            obs_peletizado = ?
            WHERE id = ?");
    $stmt->bind_param(
        'siiiiiiiiiiiisi',
        $datos['fecha'],
        $datos['id_maquina'],
        $datos['id_turno'],
        $datos['id_operario'],
        $datos['id_operario2'],
        $datos['id_color'],
        $datos['alta_retal'],
        $datos['baja'],
        $datos['refiltrado'],
        $datos['soplado'],
        $datos['torta'],
        $datos['limpieza'],
        $datos['total'],
        $datos['obs_peletizado'],
        $id
    );
    $stmt->execute();
}

/* =================================================
   ELIMINAR
================================================= */
// Eliminar registro
function eliminarProduccionPeletizado($conexion, $id){
    $stmt = $conexion->prepare("DELETE FROM PRODUCCION_PELETIZADO WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
}
