<?php
/* =================================================
   FUNCIÓN BASE
================================================= */
// Ejecutar consulta y retornar valor 'total'
function obtenerTotalRollo($conexion, $sql){
    $res = mysqli_query($conexion, $sql);
    if(!$res){
        return 0;
    }
    $row = mysqli_fetch_assoc($res);
    return $row['total'] ?? 0;
}

/* =================================================
   CONSULTAS DE TOTALES
================================================= */
// Total histórico de producción
function obtenerTotalHistoricoRollo($conexion){
    $sql = "SELECT SUM(peso_total) total
            FROM PRODUCCION_ROLLO";
    return obtenerTotalRollo($conexion, $sql);
}
// Producción de la semana actual
function obtenerProduccionSemanaRollo($conexion){
    $sql = "SELECT SUM(peso_total) total
            FROM PRODUCCION_ROLLO
            WHERE YEARWEEK(fecha_rollo,1)=YEARWEEK(CURDATE(),1)";
    return obtenerTotalRollo($conexion, $sql);
}
// Producción del mes actual
function obtenerProduccionMesRollo($conexion){
    $sql = "SELECT SUM(peso_total) total
            FROM PRODUCCION_ROLLO
            WHERE MONTH(fecha_rollo)=MONTH(CURDATE())
            AND YEAR(fecha_rollo)=YEAR(CURDATE())";
    return obtenerTotalRollo($conexion, $sql);
}

/* =================================================
   TOP MÁQUINA
================================================= */
// Máquina con más producción
function obtenerTopMaquinaRollo($conexion){
    $sql = "SELECT m.nombre_maquina,
            IFNULL(SUM(r.peso_total),0) total
            FROM PRODUCCION_ROLLO r
            LEFT JOIN MAQUINAS m
                ON r.id_maquina = m.id_maquina
            WHERE YEAR(r.fecha_rollo)=YEAR(CURDATE())
            GROUP BY r.id_maquina
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return [
        "nombre_maquina" => "Sin datos",
        "total" => 0
    ];
}

/* =================================================
   TOP OPERARIO
================================================= */
// Operario con más producción
function obtenerTopOperarioRollo($conexion){
    $sql = "SELECT o.nombre_operario,
            IFNULL(SUM(r.peso_total),0) total
            FROM PRODUCCION_ROLLO r
            LEFT JOIN OPERARIOS o
                ON r.id_operario = o.id_operario
            WHERE YEAR(r.fecha_rollo)=YEAR(CURDATE())
            GROUP BY r.id_operario
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return [
        "nombre_operario" => "Sin datos",
        "total" => 0
    ];
}

/* =================================================
   TOTAL MES
================================================= */
// Total de rollos del mes
function obtenerTotalMesRollo($conexion,$mes){
    $sql = "SELECT SUM(peso_total) total
            FROM PRODUCCION_ROLLO
            WHERE MONTH(fecha_rollo) = $mes
            AND YEAR(fecha_rollo)=YEAR(CURDATE())";
    return obtenerTotalRollo($conexion, $sql);
}

/* =================================================
   RESUMEN MES
================================================= */
// Resumen de producción del mes
function obtenerResumenMesRollo($conexion,$mes){
    $sql = "SELECT SUM(peso_rollo) peso_rollo, SUM(peso_retal) peso_retal, SUM(peso_total) peso_total
            FROM PRODUCCION_ROLLO
            WHERE MONTH(fecha_rollo)= $mes
            AND YEAR(fecha_rollo)=YEAR(CURDATE())";
    $res = mysqli_query($conexion, $sql);
    if($res){
        $row = mysqli_fetch_assoc($res);
        return [
            'peso_rollo' => $row['peso_rollo'] ?? null,
            'peso_retal' => $row['peso_retal'] ?? null,
            'peso_total' => $row['peso_total'] ?? null
        ];
    }
    return ['peso_rollo' => null, 'peso_retal' => null, 'peso_total' => null];
}

/* =================================================
   MEJOR Y PEOR DÍA
================================================= */
// Mejor y peor día de producción del mes
function obtenerMejorPeorDiaMesRollo($conexion,$mes){
    $sql = "SELECT 
                DATE(fecha_rollo) fecha, SUM(peso_total) total
            FROM PRODUCCION_ROLLO
            WHERE MONTH(fecha_rollo) = $mes
            AND YEAR(fecha_rollo)=YEAR(CURDATE())
            GROUP BY DATE(fecha_rollo)";
    $res = mysqli_query($conexion, $sql);
    $mejor = null;
    $peor = null;
    if($res && mysqli_num_rows($res) > 0){
        while($row = mysqli_fetch_assoc($res)){
            if(!$mejor || $row['total'] > $mejor['total']){
                $mejor = $row;
            }
            if(!$peor || $row['total'] < $peor['total']){
                $peor = $row;
            }
        }
    }else{
        $mejor = [
            'fecha' => 'Sin datos',
            'total' => 0
        ];
        $peor = [
            'fecha' => 'Sin datos',
            'total' => 0
        ];
    }
    return [
        'mejor' => $mejor,
        'peor' => $peor
    ];
}
/* =================================================
   TOP OPERARIO
================================================= */
// Operario con más producción en el mes
function obtenerTopOperarioMesRollo($conexion,$mes){
    $sql = "SELECT o.nombre_operario,
            IFNULL(SUM(r.peso_total),0) total
            FROM PRODUCCION_ROLLO r
            LEFT JOIN OPERARIOS o
            ON r.id_operario = o.id_operario
            WHERE MONTH(r.fecha_rollo) = $mes
            AND YEAR(r.fecha_rollo)=YEAR(CURDATE())
            GROUP BY r.id_operario
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return [
        "nombre_operario" => "Sin datos",
        "total" => 0
    ];
}

/* =================================================
   TOP MÁQUINA
================================================= */
// Máquina con más producción en el mes
function obtenerTopMaquinaMesRollo($conexion,$mes){
    $sql = "SELECT m.nombre_maquina,
            IFNULL(SUM(r.peso_total),0) total
            FROM PRODUCCION_ROLLO r
            LEFT JOIN MAQUINAS m
            ON r.id_maquina = m.id_maquina
            WHERE MONTH(r.fecha_rollo) = $mes
            AND YEAR(r.fecha_rollo)=YEAR(CURDATE())
            GROUP BY r.id_maquina
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return [
        "nombre_maquina" => "Sin datos",
        "total" => 0
    ];
}

/* =================================================
   TABLAS
================================================= */
// Producción agrupada por fecha en un rango
function obtenerTablaFechasRollo($conexion, $desde, $hasta){
    $sql = "SELECT
            DATE(p.fecha_rollo) fecha, SUM(p.peso_rollo) peso_rollo, SUM(p.peso_retal) peso_retal, SUM(p.peso_total) peso_total
            FROM PRODUCCION_ROLLO p
            WHERE DATE(p.fecha_rollo)
            BETWEEN '$desde' AND '$hasta'
            GROUP BY DATE(p.fecha_rollo)
            ORDER BY fecha DESC";
    return mysqli_query($conexion, $sql);
}
// Producción agrupada por máquina en un rango
function obtenerTablaMaquinasRollo($conexion, $desde, $hasta){
    $sql = "SELECT m.nombre_maquina,
                SUM(p.peso_rollo) peso_rollo,
                SUM(p.peso_retal) peso_retal,
                SUM(p.peso_total) peso_total
            FROM PRODUCCION_ROLLO p
            LEFT JOIN MAQUINAS m
                ON p.id_maquina = m.id_maquina
            WHERE DATE(p.fecha_rollo)
                BETWEEN '$desde' AND '$hasta'
            GROUP BY m.id_maquina, m.nombre_maquina
            ORDER BY peso_total DESC";
    return mysqli_query($conexion, $sql);
}

/* =================================================
   IMPORTACIÓN
================================================= */
// Fecha de la última importación de rollos
function obtenerUltimaImportacionRollo($conexion){
    $sql = "SELECT ultimo_id_sheet
            FROM AREAS
            WHERE nombre_area = 'rollo'";
    $res = mysqli_query($conexion, $sql);
    if(!$res){
        return 'Ninguno';
    }
    $row = mysqli_fetch_assoc($res);
    return $row['ultimo_id_sheet'] ?? 'Ninguno';
}
