<?php
// Modelo de la planilla de Mezclas

date_default_timezone_set('America/Bogota');

require_once dirname(__DIR__, 2) . '/shared/catalogosModel.php';

// Validar fecha Y-m-d
function validarFechaMezcla($fecha){
    $fecha = trim((string) $fecha);
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if($d && $d->format('Y-m-d') === $fecha){
        return $fecha;
    }
    return null;
}

// Solo letras y espacios
function nombrePropioValido($texto){
    return (bool) preg_match('/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+$/u', $texto);
}
// Cada palabra con mayúscula inicial
function capitalizarNombre($texto){
    $limpio = trim(preg_replace('/\s+/', ' ', (string) $texto));
    if($limpio === ''){
        return '';
    }
    $palabras = explode(' ', mb_strtolower($limpio, 'UTF-8'));
    foreach($palabras as &$palabra){
        $palabra = mb_strtoupper(mb_substr($palabra, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($palabra, 1, null, 'UTF-8');
    }
    return implode(' ', $palabras);
}

// Número válido o vacío (vacío = no se llenó esa columna)
function valorMezcla($valor){
    $valor = trim((string) $valor);
    if($valor === ''){
        return null;
    }
    if(!is_numeric($valor) || (float) $valor < 0){
        return false; // inválido
    }
    return (float) $valor;
}

// "4.0" -> "4", "4.5" -> "4.5" (sin ceros decimales de más)
function formatoNumeroMezcla($valor){
    $valor = (float) $valor;
    return (floor($valor) == $valor) ? (string) (int) $valor : (string) $valor;
}

// Columnas que se miden en sacos (x25), no en kg
function columnasX25Mezcla(){
    return [
        'blanco_r', 'negro_r', 'rojo_r', 'amarillo_r', 'azul_r', 'verde_r', 'naranja_r', 'marron_r', 'ladrillo_r',
        'original_b', 'fg', 'master', 'lineal',
    ];
}

// Referencias asignadas a una máquina en Relaciones > Referencias × Máquinas (área mezclas)
function referenciasDeMaquinaMezcla($conexion, $idMaquina){
    $res = $conexion->query("SELECT id_area FROM AREAS WHERE nombre_area = 'mezclas' LIMIT 1");
    $idArea = (int) ($res->fetch_assoc()['id_area'] ?? 0);
    if(!$idArea){
        return [];
    }
    $filas = obtenerReferenciasPorMaquina($conexion, $idMaquina, $idArea);
    // La tabla usa id/nombre; el resto de Mezclas espera id_referencia/nombre_referencia
    foreach($filas as &$f){
        $f['id_referencia'] = $f['id'];
        $f['nombre_referencia'] = $f['nombre'];
    }
    return $filas;
}

// Columnas de cada tabla (nombre de columna => tipo)
function columnasTabla1Mezcla(){
    return [
        'blanco_r' => 'INT', 'negro_r' => 'INT', 'rojo_r' => 'INT', 'amarillo_r' => 'INT',
        'azul_r' => 'INT', 'verde_r' => 'INT', 'naranja_r' => 'INT', 'marron_r' => 'INT', 'ladrillo_r' => 'INT',
    ];
}
function columnasTabla2Mezcla(){
    return [
        'original_b' => 'INT', 'fg' => 'INT', 'master' => 'INT', 'lineal' => 'INT',
        'deshidratante' => 'DECIMAL',
    ];
}
function columnasTabla3Mezcla(){
    return [
        'p_blanco' => 'DECIMAL', 'p_negro' => 'DECIMAL', 'p_rojo' => 'DECIMAL', 'p_amarillo' => 'DECIMAL',
        'p_azul' => 'DECIMAL', 'p_verde' => 'DECIMAL', 'p_naranja' => 'DECIMAL', 'p_marron' => 'DECIMAL',
        'p_ladrillo' => 'DECIMAL',
    ];
}

/* =================================================
   CÓDIGO Y NOMBRES DE ARCHIVO
================================================= */
// Código de la planilla: MZ{yyyyMMdd} (sin turno; único por fecha+máquina)
function construirCodigoMezcla($fecha){
    return 'MZ' . date('Ymd', strtotime($fecha));
}

// Base de id_sheet / nombre del PDF: MZ{yyyyMMdd}_M{máquina}
function baseArchivoMezcla($fecha, $nombreMaquina){
    $num = (int) preg_replace('/\D/', '', $nombreMaquina);
    return construirCodigoMezcla($fecha) . '_M' . str_pad($num, 2, '0', STR_PAD_LEFT);
}

// Nombre del PDF: MZ{yyyyMMdd}_M{máquina}.pdf (uno solo por día+máquina,
// acumula todas las mezclas de ese día; se reemplaza cada vez que se finaliza una más)
function nombrePdfMezcla($fecha, $nombreMaquina){
    return baseArchivoMezcla($fecha, $nombreMaquina) . '.pdf';
}

// id_sheet de PRODUCCION_MEZCLA: único por mezcla individual (puede haber
// varias el mismo día en la misma máquina, el PDF/código no las diferencia)
function idSheetMezcla($fecha, $nombreMaquina, $idPlanilla){
    return baseArchivoMezcla($fecha, $nombreMaquina) . '_' . $idPlanilla;
}

/* =================================================
   CONSULTAS DE PLANILLAS
================================================= */
function sqlPlanillaMezcla(){
    return "SELECT p.*, m.nombre_maquina
            FROM mezcla_sheet p
            JOIN maquinas m ON m.id_maquina = p.id_maquina";
}

function obtenerPlanillaMezcla($conexion, $id){
    $stmt = $conexion->prepare(sqlPlanillaMezcla() . " WHERE p.id_planilla = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Planilla ABIERTA de ese día+máquina (para retomarla). Varias mezclas del
// mismo día+máquina son válidas, así que las finalizadas no cuentan aquí.
function buscarPlanillaAbiertaMezcla($conexion, $codigo, $idMaquina){
    $stmt = $conexion->prepare(sqlPlanillaMezcla() . " WHERE p.codigo = ? AND p.id_maquina = ? AND p.estado = 'abierta' LIMIT 1");
    $stmt->bind_param('si', $codigo, $idMaquina);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Todas las mezclas ya finalizadas de un día+máquina, con nombres (para el PDF agregado)
function obtenerMezclasDelDiaMezcla($conexion, $fecha, $idMaquina){
    $stmt = $conexion->prepare("
        SELECT p.*, o.nombre_operario, r.nombre_referencia
        FROM produccion_mezcla p
        LEFT JOIN operarios o ON o.id_operario = p.id_operario
        LEFT JOIN referencias r ON r.id_referencia = p.id_referencia
        WHERE p.fecha_mezcla = ? AND p.id_maquina = ?
        ORDER BY p.id
    ");
    $stmt->bind_param('si', $fecha, $idMaquina);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Planillas abiertas (para continuar), opcionalmente de una sola máquina
function planillasAbiertasMezcla($conexion, $idMaquina = null){
    $sql = sqlPlanillaMezcla() . " WHERE p.estado = 'abierta'";
    if($idMaquina){
        $sql .= " AND p.id_maquina = " . (int) $idMaquina;
    }
    $sql .= " ORDER BY p.creado_en DESC";
    return $conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function crearPlanillaMezcla($conexion, $codigo, $fecha, $idMaquina){
    $stmt = $conexion->prepare("INSERT INTO mezcla_sheet (codigo, fecha_planilla, id_maquina) VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $codigo, $fecha, $idMaquina);
    $stmt->execute();
    return obtenerPlanillaMezcla($conexion, $conexion->insert_id);
}

function cancelarPlanillaMezcla($conexion, $id){
    $stmt = $conexion->prepare("DELETE FROM mezcla_sheet WHERE id_planilla = ? AND estado = 'abierta'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
}

function finalizarPlanillaMezcla($conexion, $id, $rutaPdf){
    $stmt = $conexion->prepare("UPDATE mezcla_sheet SET estado = 'finalizada', ruta_pdf = ?, finalizado_en = NOW() WHERE id_planilla = ? AND estado = 'abierta'");
    $stmt->bind_param('si', $rutaPdf, $id);
    $stmt->execute();
}

// Nombre de catálogo por id
function nombreCatalogoMezcla($conexion, $tabla, $columnaId, $columnaNombre, $id){
    $stmt = $conexion->prepare("SELECT {$columnaNombre} AS n FROM {$tabla} WHERE {$columnaId} = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    return $fila['n'] ?? '';
}

/* =================================================
   GUARDAR LA MEZCLA (al finalizar)
================================================= */
function guardarMezcla($conexion, $idSheet, $datos){
    $columnas = array_merge(array_keys(columnasTabla1Mezcla()), array_keys(columnasTabla2Mezcla()), array_keys(columnasTabla3Mezcla()));

    $campos = ['id_sheet','fecha_mezcla','id_maquina','id_operario','id_referencia','observaciones'];
    $placeholders = ['?','?','?','?','?','?'];
    $tipos = 'ssiiis';
    $valores = [$idSheet, $datos['fecha'], $datos['id_maquina'], $datos['id_operario'], $datos['id_referencia'], $datos['observaciones']];

    foreach($columnas as $col){
        $campos[] = $col;
        $placeholders[] = '?';
        $tipos .= 'd';
        $valores[] = $datos[$col];
    }

    $sql = 'INSERT INTO PRODUCCION_MEZCLA (' . implode(',', $campos) . ') VALUES (' . implode(',', $placeholders) . ')';
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($tipos, ...$valores);
    $stmt->execute();
}
