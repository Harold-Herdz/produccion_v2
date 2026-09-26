<?php
/* =================================================
   FUNCIÓN BASE
================================================= */
// Consulta que retorna 'total'
function obtenerTotalExtrusion($conexion, $sql){
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
function obtenerTotalHistoricoExtrusion($conexion){
    $sql = "SELECT SUM(peso_total) total 
            FROM PRODUCCION_EXTRUSION";
    return obtenerTotalExtrusion($conexion, $sql);
}
// Producción de la semana actual
function obtenerProduccionSemanaExtrusion($conexion){
    $sql = "SELECT SUM(peso_total) total
            FROM PRODUCCION_EXTRUSION
            WHERE YEARWEEK(fecha_extrusion, 1) = YEARWEEK(CURDATE(), 1)";
    return obtenerTotalExtrusion($conexion, $sql);
}
// Producción del mes actual
function obtenerProduccionMesExtrusion($conexion){
    $sql = "SELECT SUM(peso_total) total
            FROM PRODUCCION_EXTRUSION
            WHERE MONTH(fecha_extrusion) = MONTH(CURDATE())
            AND YEAR(fecha_extrusion) = YEAR(CURDATE())";
    return obtenerTotalExtrusion($conexion, $sql);
}

/* =================================================
   TOP MÁQUINA
================================================= */
// Máquina con más producción
function obtenerTopMaquinaExtrusion($conexion){
    $sql = "SELECT m.nombre_maquina, 
            IFNULL(SUM(e.peso_total),0) total
            FROM PRODUCCION_EXTRUSION e
            LEFT JOIN MAQUINAS m 
            ON e.id_maquina = m.id_maquina
            WHERE YEAR(e.fecha_extrusion)=YEAR(CURDATE())
            GROUP BY e.id_maquina
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return [
        'nombre_maquina' => 'Sin datos',
        'total' => 0
    ];
}

/* =================================================
   TOP OPERARIO
================================================= */
// Operador con más producción
function obtenerTopOperarioExtrusion($conexion){
    $sql = "SELECT o.nombre_operador AS nombre_operario,
            IFNULL(SUM(e.peso_total),0) total
            FROM PRODUCCION_EXTRUSION e
            LEFT JOIN OPERADORES o
            ON e.id_operador = o.id_operador
            WHERE YEAR(e.fecha_extrusion)=YEAR(CURDATE())
            GROUP BY e.id_operador
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return [
        'nombre_operario' => 'Sin datos',
        'total' => 0
    ];
}

/* =================================================
   TOTAL MES
================================================= */
// Total de paquetes del mes
function obtenerTotalMesExtrusion($conexion,$mes){
    $sql = "SELECT SUM(peso_total) total
            FROM PRODUCCION_EXTRUSION
            WHERE MONTH(fecha_extrusion) = $mes
            AND YEAR(fecha_extrusion)=YEAR(CURDATE())";
    return obtenerTotalExtrusion($conexion, $sql);
}

/* =================================================
   ROLLOS MES
================================================= */
// Rollos producidos en el mes
function obtenerRollosMesExtrusion($conexion,$mes){
    $sql = "SELECT SUM(rollos) rollos
            FROM PRODUCCION_EXTRUSION
            WHERE MONTH(fecha_extrusion) = $mes
            AND YEAR(fecha_extrusion)=YEAR(CURDATE())";
    $res = mysqli_query($conexion, $sql);
    if($res){
        $row = mysqli_fetch_assoc($res);
        return [
            'rollos' => $row['rollos'] ?? null,
        ];
    }
    return ['rollos' => null];
}

/* =================================================
   MEJOR Y PEOR DÍA
================================================= */
// Mejor y peor día
function obtenerMejorPeorDiaMesExtrusion($conexion,$mes){
    $sql = "SELECT DATE(fecha_extrusion) fecha, SUM(peso_total) total
            FROM PRODUCCION_EXTRUSION
            WHERE MONTH(fecha_extrusion) = $mes
            AND YEAR(fecha_extrusion)=YEAR(CURDATE())
            GROUP BY DATE(fecha_extrusion)";
    $res = mysqli_query($conexion, $sql);
    $mejor = ['fecha' => 'Sin datos', 'total' => 0];
    $peor = ['fecha' => 'Sin datos', 'total' => 0];
    if($res && mysqli_num_rows($res) > 0){
        while($row = mysqli_fetch_assoc($res)){
            if($row['total'] > $mejor['total']){
                $mejor = $row;
            }
            if($peor['fecha'] === 'Sin datos' || $row['total'] < $peor['total']){
                $peor = $row;
            }
        }
    }
    return [
        'mejor' => $mejor,
        'peor' => $peor
    ];
}

/* =================================================
   TOP MÁQUINA
================================================= */
// Máquina líder del mes
function obtenerTopMaquinaMesExtrusion($conexion,$mes){
    $sql = "SELECT m.nombre_maquina, 
            IFNULL(SUM(e.peso_total),0) total
            FROM PRODUCCION_EXTRUSION e
            LEFT JOIN MAQUINAS m 
            ON e.id_maquina = m.id_maquina
            WHERE MONTH(e.fecha_extrusion) = $mes
            AND YEAR(e.fecha_extrusion)=YEAR(CURDATE())
            GROUP BY e.id_maquina
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return [
        'nombre_maquina' => 'Sin datos',
        'total' => 0
    ];
}

/* =================================================
   TABLAS
================================================= */
// Producción por fecha
function obtenerTablaFechasExtrusion($conexion,$desde,$hasta){
    $sql = "SELECT 
            DATE(fecha_extrusion) fecha, SUM(rollos) rollos, SUM(peso_total) total
            FROM PRODUCCION_EXTRUSION e
            WHERE DATE(e.fecha_extrusion)
            BETWEEN '$desde' AND '$hasta'
            GROUP BY DATE(e.fecha_extrusion)
            ORDER BY fecha DESC";
    return mysqli_query($conexion, $sql);
}
// Producción por máquina
function obtenerTablaMaquinasExtrusion($conexion,$desde,$hasta){
    $sql = "SELECT m.nombre_maquina, SUM(rollos) rollos, SUM(e.peso_total) total
            FROM PRODUCCION_EXTRUSION e
            LEFT JOIN MAQUINAS m
            ON e.id_maquina = m.id_maquina
            WHERE DATE(e.fecha_extrusion)
            BETWEEN '$desde' AND '$hasta'
            GROUP BY m.id_maquina, m.nombre_maquina
            ORDER BY total DESC";
    return mysqli_query($conexion, $sql);
}

/* =================================================
   IMPORTACIÓN
================================================= */
// Última importación de Extrusión
function obtenerUltimaImportacionExtrusion($conexion){
    $sql = "SELECT ultimo_id_sheet
            FROM AREAS
            WHERE nombre_area = 'extrusion'";
    $res = mysqli_query($conexion, $sql);
    if(!$res){
        return 'Ninguno';
    }
    $row = mysqli_fetch_assoc($res);
    return $row['ultimo_id_sheet'] ?? 'Ninguno';
}
