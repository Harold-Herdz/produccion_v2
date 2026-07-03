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
// Convertir marca temporal de Google Sheets a formato MySQL
function convertirMarca($fecha) {
    $fecha = trim($fecha);
    $f = DateTime::createFromFormat('d/m/Y H:i:s', $fecha);
    if (!$f) $f = DateTime::createFromFormat('d/m/Y G:i:s', $fecha);
    if (!$f) $f = DateTime::createFromFormat('d/m/Y g:i:s', $fecha);
    // Corregir hora de un solo dígito
    if (!$f) {
        $fecha = preg_replace('/(\d{2}\/\d{2}\/\d{4}) (\d):/', '$1 0$2:', $fecha);
        $f = DateTime::createFromFormat('d/m/Y H:i:s', $fecha);
    }
    return $f ? $f->format('Y-m-d H:i:s') : null;
}

// Convertir fecha en múltiples formatos posibles a formato MySQL
function convertirFecha($fecha) {
    $fecha = trim($fecha);
    if (empty($fecha)) {
        return null;
    }
    $fecha = preg_replace('/\s+/', ' ', $fecha);
    $formatos = [
        'd/m/Y H:i:s',
        'd/m/Y G:i:s',
        'd/m/Y H:i:s',
        'j/n/Y',
        'd/m/Y',
        'j/n/Y G:i:s',
        'j/n/Y H:i:s',
        'd/m/Y G:i:s',
    ];
    foreach ($formatos as $formato) {
        $f = DateTime::createFromFormat($formato, $fecha);
        if ($f !== false) {
            return $f->format('Y-m-d H:i:s');
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
// Obtener la última fecha importada del registro de control
function obtenerUltimaFecha($conexion, $nombre) {
    $res = mysqli_query($conexion, "SELECT ultima_fecha FROM IMPORTAR WHERE nombre = '$nombre'");
    $row = mysqli_fetch_assoc($res);
    return $row['ultima_fecha'] ?? null;
}
// Actualizar la última fecha importada en el registro de control
function actualizarUltimaFecha($conexion, $nombre, $nueva_fecha) {
    $sql = "UPDATE IMPORTAR SET ultima_fecha = '$nueva_fecha' WHERE nombre = '$nombre'";
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
function leerSheet($url, $modo, $ultima_fecha) {
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
            if ($ultima_fecha) {
                $fecha_fila = strtotime($data[1]);
                $ultima     = strtotime($ultima_fecha);

                if ($fecha_fila <= $ultima) {
                    $omitidas++;
                    continue;
                }
            }
        }
        $filas[] = $data;
    }
    fclose($archivo);
    return [$filas, $omitidas];
}
// Ejecutar SQL, clasificar resultado e informar progreso al navegador
function procesarFila($conexion, $sql, $fecha, &$contador, $total, &$insertados, &$actualizados, &$duplicados, &$nueva_fecha) {
    $contador++;

    mysqli_query($conexion, $sql);
    $rows = mysqli_affected_rows($conexion);
    // Actualizar la fecha más reciente procesada
    if ($fecha && (!isset($nueva_fecha) || $fecha > $nueva_fecha)) {
        $nueva_fecha = $fecha;
    }
    // Clasificar resultado según filas afectadas
    if ($rows === 1) {
        $insertados++;
        $tipo   = 'ok';
        $logMsg = addslashes("✔ Insertado · $fecha");
    } elseif ($rows === 2) {
        $actualizados++;
        $tipo   = 'upd';
        $logMsg = addslashes("↻ Actualizado · $fecha");
    } else {
        $duplicados++;
        $tipo   = 'dup';
        $logMsg = addslashes("⚠ Duplicado · $fecha");
    }
    // Enviar resultado al navegador en tiempo real
    echo "<script>tick($contador,$total,$insertados,$actualizados,$duplicados,'$logMsg','$tipo');</script>\n";
    if (ob_get_level()) ob_flush();
    flush();
}
// Guardar última fecha e enviar señal de finalización al navegador
function finalizarImportacion($conexion, $nombre, $nueva_fecha, $insertados, $actualizados, $duplicados, $total) {
    if (!empty($nueva_fecha)) {
        actualizarUltimaFecha($conexion, $nombre, $nueva_fecha);
    }
    echo "<script>done($insertados,$actualizados,$duplicados,$total);</script>\n";
    if (ob_get_level()) ob_flush();
    flush();
}