<?php
/* =================================================
   CONSULTAS
================================================= */
// Registro por ID
function obtenerProduccionPorId($conexion, $id){
    $stmt = $conexion->prepare("SELECT * FROM PRODUCCION_SELLADO WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Catálogos para selectores del formulario
function obtenerMaquinas($conexion){
    return mysqli_query($conexion,
        "SELECT * FROM MAQUINAS ORDER BY CAST(REGEXP_SUBSTR(nombre_maquina, '[0-9]+') AS UNSIGNED)"
    );
}
function obtenerOperarios($conexion){
    return mysqli_query($conexion,
        "SELECT * FROM OPERARIOS"
    );
}
function obtenerReferencias($conexion){
    return mysqli_query($conexion,
        "SELECT * FROM REFERENCIAS ORDER BY CAST(REPLACE(REPLACE(nombre_referencia, ',', '.'), 'K', '') AS DECIMAL(10,2))"
    );
}
// Referencias especiales (para registros que no usan el catálogo K)
function obtenerReferenciasEsp($conexion){
    return mysqli_query($conexion, "
        SELECT * FROM REFERENCIAS_ESP
        ORDER BY
            CASE WHEN nombre_referencia_esp REGEXP '^[0-9]+(,[0-9]+)?K ESP$' THEN 0 ELSE 1 END,
            CASE WHEN nombre_referencia_esp REGEXP '^[0-9]+(,[0-9]+)?K ESP$'
                 THEN CAST(REPLACE(REPLACE(nombre_referencia_esp, ',', '.'), 'K ESP', '') AS DECIMAL(10,2)) END,
            nombre_referencia_esp
    ");
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
function obtenerJornadas($conexion){
    return mysqli_query($conexion,
        "SELECT * FROM JORNADAS"
    );
}

/* =================================================
   ACTUALIZAR
================================================= */
// Actualizar registro (referencia normal/especial es opcional entre sí)
function actualizarProduccion($conexion, $id, $datos){
    $stmt = $conexion->prepare("UPDATE PRODUCCION_SELLADO SET
            fecha_sellado = ?,
            id_operario = ?,
            id_maquina = ?,
            id_referencia = ?,
            id_referencia_esp = ?,
            id_color = ?,
            id_turno = ?,
            id_jornada = ?,
            paquetes_x70 = ?,
            paquetes_x90 = ?,
            paquetes_x98 = ?,
            peso_hora1 = ?,
            peso_hora2 = ?,
            peso_hora3 = ?,
            peso_hora4 = ?,
            peso_hora5 = ?,
            obs_sellado = ?
            WHERE id = ?");
    $stmt->bind_param(
        'siiiiiiiiiidddddsi',
        $datos['fecha'],
        $datos['id_operario'],
        $datos['id_maquina'],
        $datos['id_referencia'],
        $datos['id_referencia_esp'],
        $datos['id_color'],
        $datos['id_turno'],
        $datos['id_jornada'],
        $datos['paq_x70'],
        $datos['paq_x90'],
        $datos['paq_x98'],
        $datos['peso_h1'],
        $datos['peso_h2'],
        $datos['peso_h3'],
        $datos['peso_h4'],
        $datos['peso_h5'],
        $datos['obs_sellado'],
        $id
    );
    $stmt->execute();
}

/* =================================================
   ELIMINAR
================================================= */
// Eliminar registro
function eliminarProduccion($conexion, $id){
    $stmt = $conexion->prepare("DELETE FROM PRODUCCION_SELLADO WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
}
