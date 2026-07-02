<?php
/* =================================================
   CONSULTAS
================================================= */
// Obtener registro de producción por ID
function obtenerProduccionPorId($conexion, $id){
    $sql = "SELECT * 
            FROM PRODUCCION_SELLADO 
            WHERE id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

// Catálogos para selectores del formulario
function obtenerOperarios($conexion){
    return mysqli_query($conexion,
        "SELECT * FROM OPERARIOS"
    );
}
function obtenerMaquinas($conexion){
    return mysqli_query($conexion,
        "SELECT * FROM MAQUINAS"
    );
}
function obtenerReferencias($conexion){
    return mysqli_query($conexion,
        "SELECT * FROM REFERENCIAS"
    );
}
function obtenerColores($conexion){
    return mysqli_query($conexion,
        "SELECT * FROM COLORES"
    );
}
function obtenerTurnos($conexion){
    return mysqli_query($conexion,
        "SELECT * FROM TURNOS"
    );
}

/* =================================================
   ACTUALIZAR
================================================= */
// Actualizar registro de producción por ID
function actualizarProduccion($conexion, $id, $datos){
    $sql = "UPDATE PRODUCCION_SELLADO SET
            fecha_paq='{$datos['fecha']}',
            id_operario='{$datos['id_operario']}',
            id_maquina='{$datos['id_maquina']}',
            id_referencia='{$datos['id_referencia']}',
            id_color='{$datos['id_color']}',
            id_turno='{$datos['id_turno']}',
            paquetes_x70='{$datos['paq_x70']}',
            paquetes_x90='{$datos['paq_x90']}',
            paquetes_x98='{$datos['paq_x98']}',
            observaciones_paq='{$datos['observaciones']}'
            WHERE id=$id";
    mysqli_query($conexion, $sql);
}

/* =================================================
   ELIMINAR
================================================= */
// Eliminar registro de producción por ID
function eliminarProduccion($conexion, $id){
    $sql = "DELETE FROM PRODUCCION_SELLADO 
            WHERE id = $id";
    mysqli_query($conexion, $sql);
}