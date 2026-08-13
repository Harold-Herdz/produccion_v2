<?php
// Límite de memoria y tiempo de ejecución
ini_set('memory_limit', '512M');
set_time_limit(0);

/* Desactivar compresión */
if(function_exists('apache_setenv')){
    @apache_setenv('no-gzip', 1);
}

@ini_set('zlib.output_compression', 0);
@ini_set('output_buffering', 0);

/* =====================
   FUNCIONES DE TEXTO
===================== */
// Limpiar nombre: si tiene ' - ', retorna solo la parte derecha
function limpiarNombre($texto) {
    $texto = trim($texto);
    if (strpos($texto, ' - ') !== false) {
        $partes = explode(' - ', $texto);
        return trim($partes[1]);
    }
    return $texto;
}
// Convertir horario del formulario al bloque correspondiente
function convertirBloque($turno) {
    switch (trim($turno)) {
        case "6am - 2pm":
            return "Día";
        case "2pm - 10pm":
            return "Tarde";
        case "10pm - 6am":
            return "Noche";
        default:
            return "";
    }
}
// Convertir número con formato colombiano (puntos y comas) a float
function convertirNumero($valor) {
    $valor = trim($valor);
    if ($valor === '' || $valor === null) {
        return 0;
    }
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
    return (float)$valor;
}


/* =====================
   FUNCIONES DE FECHA
===================== */
// Convertir fecha en múltiples formatos posibles a formato MySQL
function convertirFecha($fecha) {
    $fecha = trim($fecha);
    if (empty($fecha)) {
        return null;
    }
    $formatos = ['d/m/Y', 'j/n/Y', 'j/m/Y', 'd/n/Y'];
    foreach ($formatos as $formato) {
        $f = DateTime::createFromFormat($formato, $fecha);
        if ($f !== false) {
            return $f->format('Y-m-d'); 
        }
    }
    return null;
}

/* =====================
   FUNCIONES DE BD
===================== */
// Cargar catálogo como arreglo asociativo [nombre => id]
function cargarCatalogo($conexion, $tabla, $campo_nombre, $campo_id) {
    $lista = [];
    $res = mysqli_query($conexion, "SELECT $campo_id, $campo_nombre FROM $tabla");
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[trim($row[$campo_nombre])] = $row[$campo_id];
    }
    return $lista;
}
// Insertar nuevo registro en catálogo si no existe y retornar su ID
function autoCrear($conexion, &$catalogo, $tabla, $campo, $valor) {
    $valor_esc = mysqli_real_escape_string($conexion, $valor);
    mysqli_query($conexion, "INSERT INTO $tabla ($campo) VALUES ('$valor_esc')");
    $id = mysqli_insert_id($conexion);
    $catalogo[$valor] = $id;
    return $id;
}
// Obtener el último id_sheet importado
function obtenerUltimoIdSheet($conexion, $nombre) {
    $res = mysqli_query($conexion, "SELECT ultimo_id_sheet FROM IMPORTAR WHERE nombre = '$nombre'");
    $row = mysqli_fetch_assoc($res);
    return $row['ultimo_id_sheet'] ?? null;
}
// Actualizar el último id_sheet importado
function actualizarUltimoIdSheet($conexion, $nombre, $id_sheet) {
    $sql = "UPDATE IMPORTAR
            SET ultimo_id_sheet = '$id_sheet'
            WHERE nombre = '$nombre'";
    mysqli_query($conexion, $sql);
}

/* =====================
   FUNCIONES DE PROGRESO
===================== */
// Enviar actualización de progreso al navegador en tiempo real
function sendProgress($pct, $msg) {
    $msg = addslashes($msg);
    echo "<script>up($pct,'$msg');</script>\n";
    if (ob_get_level()) ob_flush();
    flush();
}
// Leer filas del Google Sheet y filtrar según modo de importación
function leerSheet($url, $modo, $ultimo_id_sheet) {
    $archivo = fopen($url, 'r');
    if (!$archivo) {
        return [null, 0];
    }
    $filas    = [];
    $primera  = true;
    $omitidas = 0;
    while (($data = fgetcsv($archivo, 1000, ',')) !== false) {
        if ($primera) { $primera = false; continue; }

        // En modo 'nuevos', omitir filas ya importadas
        if ($modo === 'nuevos') {
            $id_sheet = trim($data[0]);
            if ($ultimo_id_sheet && strcmp($id_sheet, $ultimo_id_sheet) <= 0) {
                $omitidas++;
                continue;
            }
        }
        $filas[] = $data;
    }
    fclose($archivo);
    return [$filas, $omitidas];
}
// Ejecutar SQL, clasificar resultado e informar progreso al navegador
function procesarFila($conexion, $sql, $id_sheet, &$contador, $total,
    &$insertados, &$actualizados, &$duplicados, &$ultimo_id_sheet) {
    $contador++;

    mysqli_query($conexion, $sql);
    $rows = mysqli_affected_rows($conexion);
    // Guardar el último id_sheet procesado
    $ultimo_id_sheet = $id_sheet;
    // Clasificar resultado según filas afectadas
    if ($rows === 1) {
        $insertados++;
        $tipo   = 'ok';
        $logMsg = addslashes("✔ Insertado · $id_sheet");
    } elseif ($rows === 2) {
        $actualizados++;
        $tipo   = 'upd';
        $logMsg = addslashes("↻ Actualizado · $id_sheet");
    } else {
        $duplicados++;
        $tipo   = 'dup';
        $logMsg = addslashes("⚠ Duplicado · $id_sheet");
    }
    // Enviar resultado al navegador en tiempo real
    echo "<script>tick($contador,$total,$insertados,$actualizados,$duplicados,'$logMsg','$tipo');</script>\n";
    if (ob_get_level()) ob_flush();
    flush();
}
// Guardar última fecha e enviar señal de finalización al navegador
function finalizarImportacion($conexion, $nombre, $insertados, $actualizados, $duplicados, $total, $ultimo_id_sheet = null) {

    if (!empty($ultimo_id_sheet)) {
        actualizarUltimoIdSheet($conexion, $nombre, $ultimo_id_sheet);
    }

    echo "<script>done($insertados,$actualizados,$duplicados,$total);</script>\n";
    if (ob_get_level()) ob_flush();
    flush();
}