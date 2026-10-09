<?php
/* =================================================
   CONSULTAS
================================================= */
// Registro por ID
function obtenerProduccionPorId($conexion, $id){
    $sql = "SELECT * FROM PRODUCCION_MEZCLA WHERE id = $id";
    $res = mysqli_query($conexion, $sql);
    return mysqli_fetch_assoc($res);
}

// Catálogos para selectores del formulario
function obtenerOperarios($conexion){
    return mysqli_query($conexion, "SELECT * FROM OPERARIOS ORDER BY nombre_operario");
}
function obtenerMaquinas($conexion){
    return mysqli_query($conexion,
        "SELECT * FROM MAQUINAS ORDER BY CAST(REGEXP_SUBSTR(nombre_maquina, '[0-9]+') AS UNSIGNED)"
    );
}
function obtenerReferencias($conexion){
    return mysqli_query($conexion,
        "SELECT * FROM REFERENCIAS ORDER BY CAST(REPLACE(REPLACE(nombre_referencia, ',', '.'), 'K', '') AS DECIMAL(10,2))"
    );
}

// Columnas de valor de cada tabla (para pintar resumen e historial)
function columnasTabla1MezclaHistorial(){
    return [
        'blanco_r' => 'BLANCO R', 'negro_r' => 'NEGRO R', 'rojo_r' => 'ROJO R', 'amarillo_r' => 'AMARILLO R',
        'azul_r' => 'AZUL R', 'verde_r' => 'VERDE R', 'naranja_r' => 'NARANJA R', 'marron_r' => 'MARRÓN R', 'ladrillo_r' => 'LADRILLO R',
        'original_b' => 'ORIGINAL B', 'fg' => 'FG', 'master' => 'MASTER', 'lineal' => 'LINEAL',
    ];
}
function columnasTabla2MezclaHistorial(){
    return [
        'deshidratante' => 'DESHIDRATANTE',
        'p_blanco' => 'P. BLANCO', 'p_negro' => 'P. NEGRO', 'p_rojo' => 'P. ROJO', 'p_amarillo' => 'P. AMARILLO',
        'p_azul' => 'P. AZUL', 'p_verde' => 'P. VERDE', 'p_naranja' => 'P. NARANJA',
        'p_marron' => 'P. MARRÓN', 'p_ladrillo' => 'P. LADRILLO',
    ];
}

// Resumen de una fila: "Blanco R: 1x25, Master: 1x25"
function resumenTablaMezcla($fila, $columnas, $unidad){
    $partes = [];
    foreach($columnas as $col => $etiqueta){
        if($fila[$col] !== null && $fila[$col] !== ''){
            $valor = (float) $fila[$col];
            $texto = (floor($valor) == $valor) ? (string) (int) $valor : (string) $valor;
            $partes[] = $etiqueta . ': ' . $texto . $unidad;
        }
    }
    return $partes ? implode(', ', $partes) : '—';
}

/* =================================================
   ACTUALIZAR
================================================= */
function actualizarProduccion($conexion, $id, $datos){
    $campos = ['fecha_mezcla', 'id_operario', 'id_maquina', 'id_referencia', 'observaciones'];
    $set = [];
    foreach($campos as $c){
        $clave = ($c === 'fecha_mezcla') ? 'fecha' : $c;
        $valor = $datos[$clave];
        $set[] = "$c=" . ($valor === null ? 'NULL' : "'" . mysqli_real_escape_string($conexion, $valor) . "'");
    }
    foreach(array_merge(array_keys(columnasTabla1MezclaHistorial()), array_keys(columnasTabla2MezclaHistorial())) as $col){
        $valor = $datos[$col];
        $set[] = "$col=" . ($valor === null || $valor === '' ? 'NULL' : (float) $valor);
    }
    $sql = "UPDATE PRODUCCION_MEZCLA SET " . implode(',', $set) . " WHERE id=" . (int) $id;
    mysqli_query($conexion, $sql);
}

/* =================================================
   ELIMINAR
================================================= */
function eliminarProduccion($conexion, $id){
    $sql = "DELETE FROM PRODUCCION_MEZCLA WHERE id = $id";
    mysqli_query($conexion, $sql);
}
