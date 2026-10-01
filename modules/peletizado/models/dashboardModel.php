<?php
/* =================================================
   FUNCIÓN BASE
================================================= */
// Consulta que retorna 'total'
function obtenerTotalPeletizado($conexion, $sql){
    $res = mysqli_query($conexion, $sql);
    if(!$res){
        return 0;
    }
    $row = mysqli_fetch_assoc($res);
    return $row['total'] ?? 0;
}

/* =================================================
   DIAS TRANSCURRIDOS DEL MES
================================================= */
function obtenerDiasTranscurridosPeletizado($conexion, $mes){
    $anio = date('Y');
    $sql = "SELECT COUNT(DISTINCT DATE(fecha_peletizado))
            FROM PRODUCCION_PELETIZADO
            WHERE MONTH(fecha_peletizado) = ?
            AND YEAR(fecha_peletizado) = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $mes, $anio);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_row();
    return ($fila[0] > 0) ? $fila[0] : 0;
}

/* =================================================
   CONSULTAS DE TOTALES
================================================= */
// Total histórico de producción
function obtenerTotalHistoricoPeletizado($conexion){
    $sql = "SELECT SUM(total) total FROM PRODUCCION_PELETIZADO";
    return obtenerTotalPeletizado($conexion, $sql);
}
// Producción de la semana actual
function obtenerProduccionSemanaPeletizado($conexion){
    $sql = "SELECT SUM(total) total
            FROM PRODUCCION_PELETIZADO
            WHERE YEARWEEK(fecha_peletizado, 1) = YEARWEEK(CURDATE(), 1)";
    return obtenerTotalPeletizado($conexion, $sql);
}
// Producción del mes actual
function obtenerProduccionMesPeletizado($conexion){
    $sql = "SELECT SUM(total) total
            FROM PRODUCCION_PELETIZADO
            WHERE MONTH(fecha_peletizado) = MONTH(CURDATE())
            AND YEAR(fecha_peletizado) = YEAR(CURDATE())";
    return obtenerTotalPeletizado($conexion, $sql);
}

/* =================================================
   TOP MÁQUINA
================================================= */
// Máquina con más producción
function obtenerTopMaquinaPeletizado($conexion){
    $sql = "SELECT m.nombre_maquina,
            IFNULL(SUM(p.total),0) total
            FROM PRODUCCION_PELETIZADO p
            LEFT JOIN MAQUINAS m ON p.id_maquina = m.id_maquina
            WHERE YEAR(p.fecha_peletizado)=YEAR(CURDATE())
            GROUP BY p.id_maquina
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return ['nombre_maquina' => 'Sin datos', 'total' => 0];
}

/* =================================================
   TOP OPERARIO
   (cuenta producción tanto si salió como operario 1
   o como operario 2: se suman las dos apariciones)
================================================= */
function obtenerTopOperarioPeletizado($conexion){
    $sql = "SELECT o.nombre_operario, SUM(u.total) total
            FROM (
                SELECT id_operario id_op, total FROM PRODUCCION_PELETIZADO WHERE YEAR(fecha_peletizado)=YEAR(CURDATE())
                UNION ALL
                SELECT id_operario2 id_op, total FROM PRODUCCION_PELETIZADO WHERE YEAR(fecha_peletizado)=YEAR(CURDATE())
            ) u
            LEFT JOIN OPERARIOS o ON o.id_operario = u.id_op
            WHERE u.id_op IS NOT NULL
            GROUP BY u.id_op
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return ['nombre_operario' => 'Sin datos', 'total' => 0];
}

/* =================================================
   TOTAL MES
================================================= */
function obtenerTotalMesPeletizado($conexion, $mes){
    $sql = "SELECT SUM(total) total
            FROM PRODUCCION_PELETIZADO
            WHERE MONTH(fecha_peletizado) = $mes
            AND YEAR(fecha_peletizado)=YEAR(CURDATE())";
    return obtenerTotalPeletizado($conexion, $sql);
}

/* =================================================
   MEJOR Y PEOR DÍA
================================================= */
function obtenerMejorPeorDiaMesPeletizado($conexion, $mes){
    $sql = "SELECT DATE(fecha_peletizado) fecha, SUM(total) total
            FROM PRODUCCION_PELETIZADO
            WHERE MONTH(fecha_peletizado) = $mes
            AND YEAR(fecha_peletizado)=YEAR(CURDATE())
            GROUP BY DATE(fecha_peletizado)";
    $res = mysqli_query($conexion, $sql);
    $mejor = ['fecha' => 'Sin datos', 'total' => 0];
    $peor  = ['fecha' => 'Sin datos', 'total' => 0];
    if($res){
        while($row = mysqli_fetch_assoc($res)){
            if($row['total'] > $mejor['total']){
                $mejor = $row;
            }
            if($peor['fecha'] === 'Sin datos' || $row['total'] < $peor['total']){
                $peor = $row;
            }
        }
    }
    return ['mejor' => $mejor, 'peor' => $peor];
}

/* =================================================
   TOP MÁQUINA DEL MES
================================================= */
function obtenerTopMaquinaMesPeletizado($conexion, $mes){
    $sql = "SELECT m.nombre_maquina,
            IFNULL(SUM(p.total),0) total
            FROM PRODUCCION_PELETIZADO p
            LEFT JOIN MAQUINAS m ON p.id_maquina = m.id_maquina
            WHERE MONTH(p.fecha_peletizado) = $mes
            AND YEAR(p.fecha_peletizado)=YEAR(CURDATE())
            GROUP BY p.id_maquina
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return ['nombre_maquina' => 'Sin datos', 'total' => 0];
}

/* =================================================
   TOP OPERARIO DEL MES
================================================= */
function obtenerTopOperarioMesPeletizado($conexion, $mes){
    $sql = "SELECT o.nombre_operario, SUM(u.total) total
            FROM (
                SELECT id_operario id_op, total FROM PRODUCCION_PELETIZADO
                    WHERE MONTH(fecha_peletizado) = $mes AND YEAR(fecha_peletizado)=YEAR(CURDATE())
                UNION ALL
                SELECT id_operario2 id_op, total FROM PRODUCCION_PELETIZADO
                    WHERE MONTH(fecha_peletizado) = $mes AND YEAR(fecha_peletizado)=YEAR(CURDATE())
            ) u
            LEFT JOIN OPERARIOS o ON o.id_operario = u.id_op
            WHERE u.id_op IS NOT NULL
            GROUP BY u.id_op
            ORDER BY total DESC
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return mysqli_fetch_assoc($res);
    }
    return ['nombre_operario' => 'Sin datos', 'total' => 0];
}

/* =================================================
   TABLAS
================================================= */
// Producción por fecha
function obtenerTablaFechasPeletizado($conexion, $desde, $hasta){
    $sql = "SELECT DATE(fecha_peletizado) fecha,
                SUM(alta_retal) alta_retal, SUM(baja) baja, SUM(refiltrado) refiltrado,
                SUM(soplado) soplado, SUM(torta) torta, SUM(limpieza) limpieza, SUM(total) total
            FROM PRODUCCION_PELETIZADO
            WHERE DATE(fecha_peletizado) BETWEEN '$desde' AND '$hasta'
            GROUP BY DATE(fecha_peletizado)
            ORDER BY fecha DESC";
    return mysqli_query($conexion, $sql);
}
// Producción por operario (suma como operario 1 + operario 2)
function obtenerTablaOperariosPeletizado($conexion, $desde, $hasta){
    $sql = "SELECT o.nombre_operario,
                SUM(u.total) total
            FROM (
                SELECT id_operario id_op, total FROM PRODUCCION_PELETIZADO
                    WHERE DATE(fecha_peletizado) BETWEEN '$desde' AND '$hasta'
                UNION ALL
                SELECT id_operario2 id_op, total FROM PRODUCCION_PELETIZADO
                    WHERE DATE(fecha_peletizado) BETWEEN '$desde' AND '$hasta'
            ) u
            LEFT JOIN OPERARIOS o ON o.id_operario = u.id_op
            WHERE u.id_op IS NOT NULL
            GROUP BY u.id_op, o.nombre_operario
            ORDER BY total DESC";
    return mysqli_query($conexion, $sql);
}
// Producción por máquina
function obtenerTablaMaquinasPeletizado($conexion, $desde, $hasta){
    $sql = "SELECT m.nombre_maquina,
                SUM(p.alta_retal) alta_retal, SUM(p.baja) baja, SUM(p.refiltrado) refiltrado,
                SUM(p.soplado) soplado, SUM(p.torta) torta, SUM(p.limpieza) limpieza, SUM(p.total) total
            FROM PRODUCCION_PELETIZADO p
            LEFT JOIN MAQUINAS m ON p.id_maquina = m.id_maquina
            WHERE DATE(p.fecha_peletizado) BETWEEN '$desde' AND '$hasta'
            GROUP BY m.id_maquina, m.nombre_maquina
            ORDER BY total DESC";
    return mysqli_query($conexion, $sql);
}

/* =================================================
   IMPORTACIÓN
================================================= */
// Última importación de Peletizado
function obtenerUltimaImportacionPeletizado($conexion){
    $sql = "SELECT ultimo_id_sheet FROM AREAS WHERE nombre_area = 'peletizado'";
    $res = mysqli_query($conexion, $sql);
    if(!$res){
        return 'Ninguno';
    }
    $row = mysqli_fetch_assoc($res);
    return $row['ultimo_id_sheet'] ?? 'Ninguno';
}
