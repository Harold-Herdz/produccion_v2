<?php
// Modelo: valores de catálogo escritos a mano ("Otro") pendientes de revisión por un admin
// (campanita de notificaciones, ver templates/notificaciones.php)

// Tablas de catálogo válidas (columna id de cada una) — lista blanca, nunca se usa
// el nombre de tabla que llega de afuera sin pasar por aquí
function tablasCatalogoPendiente(){
    return [
        'operarios'       => 'id_operario',
        'referencias'     => 'id_referencia',
        'referencias_esp' => 'id_referencia_esp',
        'colores'         => 'id_color',
    ];
}

// Cantidad de valores aún sin revisar (número de la campanita)
function contarCatalogoPendientes($conexion){
    $res = $conexion->query("SELECT COUNT(*) AS total FROM catalogo_pendientes WHERE decision = 'pendiente'");
    $fila = $res->fetch_assoc();
    return (int) ($fila['total'] ?? 0);
}

// Lista para el panel de la campanita: primero lo pendiente (más nuevo arriba),
// luego el historial ya revisado (últimos 30)
function listarCatalogoPendientes($conexion){
    $pendientes = $conexion->query("
        SELECT * FROM catalogo_pendientes
        WHERE decision = 'pendiente'
        ORDER BY creado_en DESC
    ")->fetch_all(MYSQLI_ASSOC);

    $historial = $conexion->query("
        SELECT * FROM catalogo_pendientes
        WHERE decision <> 'pendiente'
        ORDER BY revisado_en DESC
        LIMIT 30
    ")->fetch_all(MYSQLI_ASSOC);

    return ['pendientes' => $pendientes, 'historial' => $historial];
}

// Confirmar: el valor queda marcado como verificado en su catálogo (ya era usable; esto solo lo valida)
function aprobarCatalogoPendiente($conexion, $id){
    $tablas = tablasCatalogoPendiente();
    $stmt = $conexion->prepare("SELECT * FROM catalogo_pendientes WHERE id = ? AND decision = 'pendiente' LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    if(!$fila || !isset($tablas[$fila['tabla']])){
        return false;
    }
    $colId = $tablas[$fila['tabla']];
    $conexion->query("UPDATE {$fila['tabla']} SET verificado = 1 WHERE {$colId} = " . (int) $fila['id_registro']);

    $usuario = $_SESSION['usuario'] ?? null;
    $upd = $conexion->prepare("UPDATE catalogo_pendientes SET decision = 'aprobado', revisado_en = NOW(), revisado_por = ? WHERE id = ?");
    $upd->bind_param('si', $usuario, $id);
    $upd->execute();
    return true;
}

// Rechazar: se oculta del catálogo (estado = 0, igual que "inhabilitar" en Catálogos) sin
// borrarlo, para no romper los registros/PDFs ya guardados que lo usaron
function rechazarCatalogoPendiente($conexion, $id){
    $tablas = tablasCatalogoPendiente();
    $stmt = $conexion->prepare("SELECT * FROM catalogo_pendientes WHERE id = ? AND decision = 'pendiente' LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    if(!$fila || !isset($tablas[$fila['tabla']])){
        return false;
    }
    $colId = $tablas[$fila['tabla']];
    $conexion->query("UPDATE {$fila['tabla']} SET estado = 0, verificado = 1 WHERE {$colId} = " . (int) $fila['id_registro']);

    $usuario = $_SESSION['usuario'] ?? null;
    $upd = $conexion->prepare("UPDATE catalogo_pendientes SET decision = 'rechazado', revisado_en = NOW(), revisado_por = ? WHERE id = ?");
    $upd->bind_param('si', $usuario, $id);
    $upd->execute();
    return true;
}
