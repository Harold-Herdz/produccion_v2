<?php
// Catálogos compartidos de Referencia/Color/Máquina y resolución de "Otro"
// (texto libre -> busca o crea en el catálogo)

/* =================================================
   REFERENCIAS POR MÁQUINA
   Cada máquina solo produce un subconjunto de referencias (ver MAQUINA_REFERENCIAS).
   Las máquinas marcadas usa_referencias_esp = 1 ("Bolsa Basura") usan el catálogo
   completo de Referencias Especiales en su lugar, nunca el de Referencias normal.
   Todas las filas devueltas usan las claves genéricas 'id'/'nombre'.
================================================= */
// Referencias normales asignadas a una máquina (catálogo cerrado: se administra en la BD)
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

// Catálogo completo de Referencias Especiales (máquinas "Bolsa Basura")
function obtenerReferenciasEspOrdenadas($conexion){
    $res = $conexion->query("
        SELECT id_referencia_esp AS id, nombre_referencia_esp AS nombre
        FROM referencias_esp
        WHERE estado = 1
        ORDER BY nombre_referencia_esp
    ");
    return $res->fetch_all(MYSQLI_ASSOC);
}

// Máquinas de un área (Sellado/Rollo/...), con su lista de referencias ya resuelta.
// Devuelve ['maquinas' => [...], 'mapaJs' => [id_maquina => ['esp'=>bool,'opciones'=>[...]]]]
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

// Colores activos; los que empiezan con "R " (retal) van al final de la lista
function obtenerColoresOrdenados($conexion){
    $res = $conexion->query("
        SELECT id_color, nombre_color
        FROM colores
        WHERE estado = 1 AND nombre_color <> ''
        ORDER BY (nombre_color LIKE 'R %') ASC, nombre_color ASC
    ");
    return $res->fetch_all(MYSQLI_ASSOC);
}

// Busca un id de catálogo por nombre exacto; lo crea si no existe. Devuelve [$id, $fueCreado]
// $nombre nuevo se crea con verificado = 0 (pendiente de revisión por un admin;
// ver el aviso/notificación de catálogo). Ya existente no se toca.
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

// Registra en CATALOGO_PENDIENTES un valor de catálogo creado a mano ("Otro"), para
// que un admin lo revise desde la campanita de notificaciones. No detiene el guardado:
// el valor ya quedó creado y disponible, esto solo deja constancia para revisarlo después.
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

// Resuelve un valor de formulario de catálogo: numérico = id existente, texto = "Otro"
// escrito a mano (busca o crea). $etiqueta ya incluye el género: "nuevo operario",
// "nueva referencia", "nuevo color". $modulo identifica el formulario de origen
// ("sellado", "rollo", ...) para la campanita de notificaciones. Devuelve [$id, $avisoONulo]
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
// Normaliza cualquier texto de jornada a uno de los 2 valores cerrados del catálogo.
// Cualquier valor que no sea exactamente "12 Horas" (sin importar mayúsculas/espacios)
// -incluyendo horarios sueltos como "6pm", "1pm", texto vacío, etc.- cae siempre en "8 Horas".
function normalizarJornada($valor){
    $v = strtolower(trim((string) $valor));
    return ($v === '12 horas') ? '12 Horas' : '8 Horas';
}

// Id de jornada a partir de cualquier texto crudo, ya normalizado a 8/12 Horas.
// JORNADAS es un catálogo cerrado de 2 valores fijos llenado a mano; nunca se crea uno aquí.
function resolverIdJornada($conexion, $valorCrudo){
    $nombre = normalizarJornada($valorCrudo);
    $stmt = $conexion->prepare("SELECT id_jornada FROM jornadas WHERE nombre_jornada = ? LIMIT 1");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    return $fila ? (int) $fila['id_jornada'] : null;
}
