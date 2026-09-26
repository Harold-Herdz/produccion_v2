<?php
// Catálogos compartidos y resolver Otro

// Referencias por máquina
// Referencias de una máquina
function obtenerReferenciasPorMaquina($conexion, $idMaquina){
    $stmt = $conexion->prepare("
        SELECT r.id_referencia AS id, r.nombre_referencia AS nombre
        FROM referencias r
        JOIN maquina_referencias mr ON mr.id_referencia = r.id_referencia
        WHERE mr.id_maquina = ? AND r.estado = 1
        ORDER BY r.id_referencia
    ");
    $stmt->bind_param('i', $idMaquina);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Referencias especiales
function obtenerReferenciasEspOrdenadas($conexion){
    $res = $conexion->query("
        SELECT id_referencia_esp AS id, nombre_referencia_esp AS nombre
        FROM referencias_esp
        WHERE estado = 1
        ORDER BY nombre_referencia_esp
    ");
    return $res->fetch_all(MYSQLI_ASSOC);
}

// Máquinas de un área
function obtenerMaquinasConReferencias($conexion, $nombreArea){
    $stmt = $conexion->prepare("
        SELECT m.id_maquina, m.nombre_maquina, m.usa_referencias_esp,
               CAST(REGEXP_SUBSTR(m.nombre_maquina, '[0-9]+') AS UNSIGNED) AS numero_maquina
        FROM maquinas m
        JOIN maquina_areas ma ON ma.id_maquina = m.id_maquina
        JOIN areas a ON a.id_area = ma.id_area
        WHERE m.estado = 1 AND a.nombre_area = ?
        ORDER BY numero_maquina
    ");
    $stmt->bind_param('s', $nombreArea);
    $stmt->execute();
    $maquinas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $referenciasEsp = obtenerReferenciasEspOrdenadas($conexion);
    $mapaJs = [];
    foreach($maquinas as &$m){
        $esp = (bool) $m['usa_referencias_esp'];
        $m['referencias'] = $esp ? $referenciasEsp : obtenerReferenciasPorMaquina($conexion, $m['id_maquina']);
        $mapaJs[$m['id_maquina']] = ['esp' => $esp, 'opciones' => $m['referencias']];
    }
    unset($m);

    return ['maquinas' => $maquinas, 'mapaJs' => $mapaJs];
}

// Colores activos
function obtenerColoresOrdenados($conexion){
    $res = $conexion->query("
        SELECT id_color, nombre_color
        FROM colores
        WHERE estado = 1 AND nombre_color <> ''
        ORDER BY (nombre_color LIKE 'R %') ASC, nombre_color ASC
    ");
    return $res->fetch_all(MYSQLI_ASSOC);
}

// Buscar o crear en catálogo
function resolverCatalogoIdONuevo($conexion, $tabla, $colId, $colNombre, $nombre){
    $nombre = trim($nombre);
    $stmt = $conexion->prepare("SELECT {$colId} AS id FROM {$tabla} WHERE {$colNombre} = ? LIMIT 1");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    if($fila){
        return [(int) $fila['id'], false];
    }
    $stmt = $conexion->prepare("INSERT INTO {$tabla} ({$colNombre}, verificado) VALUES (?, 0)");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    return [$conexion->insert_id, true];
}

// Registrar valor pendiente
function registrarCatalogoPendiente($conexion, $tabla, $idRegistro, $valor, $etiqueta, $contexto, $modulo){
    $idUsuario     = $_SESSION['id_usuario'] ?? null;
    $usuarioNombre = $_SESSION['usuario'] ?? null;
    $contexto      = ($contexto !== '') ? $contexto : null;
    $stmt = $conexion->prepare("
        INSERT INTO catalogo_pendientes (tabla, id_registro, valor, etiqueta, contexto, modulo, id_usuario, usuario_nombre)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('sissssis', $tabla, $idRegistro, $valor, $etiqueta, $contexto, $modulo, $idUsuario, $usuarioNombre);
    $stmt->execute();
}

// Resolver valor de formulario
function resolverValorCatalogo($conexion, $tabla, $colId, $colNombre, $valor, $etiqueta, $contexto = '', $modulo = ''){
    $valor = trim((string) $valor);
    if($valor === '' || $valor === 'otro'){
        return [null, null];
    }
    if(is_numeric($valor)){
        return [(int) $valor, null];
    }
    [$id, $fueCreado] = resolverCatalogoIdONuevo($conexion, $tabla, $colId, $colNombre, $valor);
    $aviso = null;
    if($fueCreado){
        $aviso = "Se agregó «{$valor}» como {$etiqueta}" . ($contexto ? " ({$contexto})" : '') . ". Falta verificarlo en Catálogos.";
        registrarCatalogoPendiente($conexion, $tabla, $id, $valor, $etiqueta, $contexto, $modulo);
    }
    return [$id, $aviso];
}

/* =================================================
   JORNADA: catálogo cerrado de 2 valores (8 Horas / 12 Horas)
================================================= */
// Normalizar jornada
function normalizarJornada($valor){
    $v = strtolower(trim((string) $valor));
    return ($v === '12 horas') ? '12 Horas' : '8 Horas';
}

// Id de jornada
function resolverIdJornada($conexion, $valorCrudo){
    $nombre = normalizarJornada($valorCrudo);
    $stmt = $conexion->prepare("SELECT id_jornada FROM jornadas WHERE nombre_jornada = ? LIMIT 1");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    return $fila ? (int) $fila['id_jornada'] : null;
}
