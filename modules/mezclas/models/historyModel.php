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

// Todas las columnas numéricas de una mezcla, en el orden de la hoja REGISTROS.
// Se usan para armar el resumen de cada fila en el historial (solo lo que
// tiene dato), nunca como columnas fijas: la mayoría quedan vacías siempre.
function columnasMezclaHistorial(){
    return [
        'blanco_r' => ['Blanco R', 'x25'], 'negro_r' => ['Negro R', 'x25'], 'rojo_r' => ['Rojo R', 'x25'],
        'amarillo_r' => ['Amarillo R', 'x25'], 'azul_r' => ['Azul R', 'x25'], 'verde_r' => ['Verde R', 'x25'],
        'naranja_r' => ['Naranja R', 'x25'], 'marron_r' => ['Marrón R', 'x25'], 'ladrillo_r' => ['Ladrillo R', 'x25'],
        'original_b' => ['Original B', 'x25'], 'fg' => ['FG', 'x25'], 'master' => ['Master', 'x25'], 'lineal' => ['Lineal', 'x25'],
        'deshidratante' => ['Deshidratante', 'kg'],
        'p_blanco' => ['P. Blanco', 'kg'], 'p_negro' => ['P. Negro', 'kg'], 'p_rojo' => ['P. Rojo', 'kg'],
        'p_amarillo' => ['P. Amarillo', 'kg'], 'p_azul' => ['P. Azul', 'kg'], 'p_verde' => ['P. Verde', 'kg'],
        'p_naranja' => ['P. Naranja', 'kg'], 'p_marron' => ['P. Marrón', 'kg'], 'p_ladrillo' => ['P. Ladrillo', 'kg'],
    ];
}

// Valor de un campo ya formateado: "4x25", "17 kg", o vacío si no tiene dato
function celdaValorMezclaHistorial($valor, $unidad){
    if($valor === null || $valor === ''){
        return '';
    }
    $num = (float) $valor;
    $texto = (floor($num) == $num) ? (string) (int) $num : (string) $num;
    $separador = ($unidad === 'x25') ? '' : ' ';
    return $texto . $separador . $unidad;
}

// Resumen de una fila: solo los campos con dato, uno por línea
// ("Blanco R: 4x25", "Original B: 6x25", "P. Blanco: 17 kg"...)
function resumenMezclaHistorial($fila){
    $partes = [];
    foreach(columnasMezclaHistorial() as $col => [$etiqueta, $unidad]){
        $valor = celdaValorMezclaHistorial($fila[$col] ?? null, $unidad);
        if($valor !== ''){
            $partes[] = $etiqueta . ': ' . $valor;
        }
    }
    return $partes ? implode('<br>', $partes) : '—';
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
    foreach(array_keys(columnasMezclaHistorial()) as $col){
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
