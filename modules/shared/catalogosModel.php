<?php
// Catálogos compartidos de Referencia/Color: orden curado para los formularios
// y resolución de "Otro" (texto libre -> busca o crea en el catálogo)

// Orden de referencias en los formularios; Rollo usa solo hasta '50K' (ver $soloHastaKg)
const ORDEN_REFERENCIAS = [
    '1.5K', '2K', '3K', '5K', '10K', '15K', '20K', '25K', '30K', '50K',
    '19x25', '23x40', '23x44', '24x32', '24+4+4', '24+7+7', '25x0,72', '25x31', '26x36', '26+4+4',
    '28x40', '32x2', '32x44', '39x50', '45x45', '50x88', '65x88', '65x90', '65x100',
    '80x1,10', '90x1,10', '95x1,10', 'Extra Jumbo', 'Jumbo', 'N N', 'K K',
];
const CANTIDAD_REFERENCIAS_ROLLO = 10; // primeras N de ORDEN_REFERENCIAS = hasta '50K'

// Referencias activas en el orden curado de arriba (ignora las que no estén en la lista)
function obtenerReferenciasOrdenadas($conexion, $soloHastaKg = false){
    $nombres = $soloHastaKg ? array_slice(ORDEN_REFERENCIAS, 0, CANTIDAD_REFERENCIAS_ROLLO) : ORDEN_REFERENCIAS;
    $res = $conexion->query("SELECT id_referencia, nombre_referencia FROM referencias WHERE estado = 1");
    $porNombre = [];
    while($fila = $res->fetch_assoc()){
        $porNombre[$fila['nombre_referencia']] = $fila;
    }
    $lista = [];
    foreach($nombres as $nombre){
        if(isset($porNombre[$nombre])){
            $lista[] = $porNombre[$nombre];
        }
    }
    return $lista;
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
function resolverCatalogoIdONuevo($conexion, $tabla, $colId, $colNombre, $nombre){
    $nombre = trim($nombre);
    $stmt = $conexion->prepare("SELECT {$colId} AS id FROM {$tabla} WHERE {$colNombre} = ? LIMIT 1");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    if($fila){
        return [(int) $fila['id'], false];
    }
    $stmt = $conexion->prepare("INSERT INTO {$tabla} ({$colNombre}) VALUES (?)");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    return [$conexion->insert_id, true];
}

// Resuelve un valor de formulario de catálogo: numérico = id existente, texto = "Otro"
// escrito a mano (busca o crea). $etiqueta ya incluye el género: "nuevo operario",
// "nueva referencia", "nuevo color". Devuelve [$id, $avisoONulo]
function resolverValorCatalogo($conexion, $tabla, $colId, $colNombre, $valor, $etiqueta, $contexto = ''){
    $valor = trim((string) $valor);
    if($valor === '' || $valor === 'otro'){
        return [null, null];
    }
    if(is_numeric($valor)){
        return [(int) $valor, null];
    }
    [$id, $fueCreado] = resolverCatalogoIdONuevo($conexion, $tabla, $colId, $colNombre, $valor);
    $aviso = $fueCreado
        ? "Se agregó «{$valor}» como {$etiqueta}" . ($contexto ? " ({$contexto})" : '') . ". Falta verificarlo en Catálogos."
        : null;
    return [$id, $aviso];
}
