<?php
/* =================================================
   CONSULTAS
================================================= */
// Registro por ID
function obtenerRegistroExtrusionPorId($conexion, $id){
    $stmt = $conexion->prepare("SELECT * FROM PRODUCCION_EXTRUSION WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
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
    return mysqli_query($conexion, "
        SELECT * FROM REFERENCIAS
        ORDER BY CAST(REPLACE(REPLACE(nombre_referencia, ',', '.'), 'K', '') AS DECIMAL(10,2))
    ");
}
// Referencias especiales (para registros que no usan el catálogo K)
function obtenerReferenciasEspExtrusion($conexion){
    return mysqli_query($conexion, "
        SELECT * FROM REFERENCIAS_ESP
        ORDER BY
            CASE WHEN nombre_referencia_esp REGEXP '^[0-9]+(,[0-9]+)?K ESP$' THEN 0 ELSE 1 END,
            CASE WHEN nombre_referencia_esp REGEXP '^[0-9]+(,[0-9]+)?K ESP$'
                 THEN CAST(REPLACE(REPLACE(nombre_referencia_esp, ',', '.'), 'K ESP', '') AS DECIMAL(10,2)) END,
            nombre_referencia_esp
    ");
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
// Actualizar registro (referencia normal/especial y lámina P son opcionales)
function actualizarProduccion($conexion, $id, $datos){
    $stmt = $conexion->prepare("UPDATE PRODUCCION_EXTRUSION SET
            fecha_extrusion = ?,
            id_maquina = ?,
            id_turno = ?,
            id_operador = ?,
            id_referencia = ?,
            id_referencia_esp = ?,
            id_color = ?,
            id_lamina_p = ?,
            rollos = ?,
            peso_total = ?
            WHERE id = ?");
    $stmt->bind_param(
        'siiiiiiidi',
        $datos['fecha'],
        $datos['id_maquina'],
        $datos['id_turno'],
        $datos['id_operador'],
        $datos['id_referencia'],
        $datos['id_referencia_esp'],
        $datos['id_color'],
        $datos['id_lamina_p'],
        $datos['rollos'],
        $datos['peso_total'],
        $id
    );
    $stmt->execute();
}

/* =================================================
   ELIMINAR
================================================= */
// Eliminar registro
function eliminarProduccion($conexion, $id){
    $stmt = $conexion->prepare("DELETE FROM PRODUCCION_EXTRUSION WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
}
