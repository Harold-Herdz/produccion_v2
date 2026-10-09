<?php
/* =================================================
   FUNCIÓN BASE
================================================= */
// Consulta que retorna 'total'
function obtenerTotalMezcla($conexion, $sql){
    $res = mysqli_query($conexion, $sql);
    if(!$res){
        return 0;
    }
    $row = mysqli_fetch_assoc($res);
    return $row['total'] ?? 0;
}

/* =================================================
   CONTEOS DE MEZCLAS
================================================= */
// Total histórico de mezclas registradas
function obtenerTotalHistoricoMezcla($conexion){
    $sql = "SELECT COUNT(*) total FROM PRODUCCION_MEZCLA";
    return obtenerTotalMezcla($conexion, $sql);
}
// Mezclas de la semana actual
function obtenerMezclasSemanaMezcla($conexion){
    $sql = "SELECT COUNT(*) total FROM PRODUCCION_MEZCLA
            WHERE YEARWEEK(fecha_mezcla,1)=YEARWEEK(CURDATE(),1)";
    return obtenerTotalMezcla($conexion, $sql);
}
// Mezclas del mes actual
function obtenerMezclasMesMezcla($conexion){
    $sql = "SELECT COUNT(*) total FROM PRODUCCION_MEZCLA
            WHERE MONTH(fecha_mezcla)=MONTH(CURDATE()) AND YEAR(fecha_mezcla)=YEAR(CURDATE())";
    return obtenerTotalMezcla($conexion, $sql);
}
// Mezclas de un mes puntual
function obtenerMezclasMesPuntualMezcla($conexion, $mes){
    $sql = "SELECT COUNT(*) total FROM PRODUCCION_MEZCLA
            WHERE MONTH(fecha_mezcla) = $mes AND YEAR(fecha_mezcla)=YEAR(CURDATE())";
    return obtenerTotalMezcla($conexion, $sql);
}

/* =================================================
   REFERENCIAS
================================================= */
// Referencia con más mezclas (histórico)
function obtenerReferenciaMasMezcladaMezcla($conexion){
    $sql = "SELECT r.nombre_referencia, COUNT(*) total
            FROM PRODUCCION_MEZCLA p
            LEFT JOIN REFERENCIAS r ON p.id_referencia = r.id_referencia
            GROUP BY p.id_referencia
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return ["nombre_referencia" => "Sin datos", "total" => 0];
}
// Cantidad de referencias distintas trabajadas (histórico)
function obtenerReferenciasDistintasMezcla($conexion){
    $sql = "SELECT COUNT(DISTINCT id_referencia) total FROM PRODUCCION_MEZCLA";
    return obtenerTotalMezcla($conexion, $sql);
}
// Referencia con más mezclas de un mes puntual
function obtenerReferenciaMasMezcladaMesMezcla($conexion, $mes){
    $sql = "SELECT r.nombre_referencia, COUNT(*) total
            FROM PRODUCCION_MEZCLA p
            LEFT JOIN REFERENCIAS r ON p.id_referencia = r.id_referencia
            WHERE MONTH(p.fecha_mezcla) = $mes AND YEAR(p.fecha_mezcla)=YEAR(CURDATE())
            GROUP BY p.id_referencia
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return ["nombre_referencia" => "Sin datos", "total" => 0];
}

/* =================================================
   TABLAS
================================================= */
// Mezclas por fecha
function obtenerTablaFechasMezcla($conexion, $desde, $hasta){
    $sql = "SELECT DATE(fecha_mezcla) fecha, COUNT(*) registros
            FROM PRODUCCION_MEZCLA
            WHERE DATE(fecha_mezcla) BETWEEN '$desde' AND '$hasta'
            GROUP BY DATE(fecha_mezcla)
            ORDER BY fecha DESC";
    return mysqli_query($conexion, $sql);
}
// Mezclas por referencia
function obtenerTablaReferenciasMezcla($conexion, $desde, $hasta){
    $sql = "SELECT r.nombre_referencia, COUNT(*) registros
            FROM PRODUCCION_MEZCLA p
            LEFT JOIN REFERENCIAS r ON p.id_referencia = r.id_referencia
            WHERE DATE(p.fecha_mezcla) BETWEEN '$desde' AND '$hasta'
            GROUP BY p.id_referencia, r.nombre_referencia
            ORDER BY registros DESC";
    return mysqli_query($conexion, $sql);
}
