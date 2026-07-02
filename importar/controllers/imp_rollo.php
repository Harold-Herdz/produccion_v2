<?php
/** @var mysqli $conexion */

// Importar conexion.php 
require_once dirname(__DIR__, 2) . '/includes/conexion.php';
mysqli_set_charset($conexion, "utf8mb4");
// Importar config.php
require_once dirname(__DIR__, 2) . '/includes/config.php';
// Importar importarModel.php
require_once dirname(__DIR__) . '/importarModel.php';

// Parametros de importación
$modo         = $_GET['modo'] ?? 'nuevos';
$ultima_fecha = obtenerUltimaFecha($conexion, 'rollo');

// Fuente de datos
$url = "https://docs.google.com/spreadsheets/d/1CpdDtlNVQJoSHJ7WAc0BrXP-FIohYWhMB6si1bzmOz0/export?format=csv&gid=1465480052";
$titulo     = "Producción de Rollos";
$subtitulo  = "PRODUCCION_ROLLO";
$volver_url = BASE_URL . "/modules/rollo/views/dashboard.php";

// Leer filas del Google Sheet
[$filas, $omitidas] = leerSheet($url, $modo, $ultima_fecha);
$total = count($filas);

// Importar progreso.php
include dirname(__DIR__) . '/views/progreso.php';
?>

<?php
// Sin registros nuevos
if ($total === 0 && $modo === 'nuevos') {
    echo "<script>
        document.getElementById('msg').textContent        = 'No hay registros nuevos...';
        document.getElementById('dot').className          = 'dot done';
        document.getElementById('status-lbl').textContent = 'Completado';
        document.getElementById('fill').style.width       = '100%';
        document.getElementById('pct').textContent        = '100';
        document.getElementById('counter').textContent    = '$omitidas registros ya importados';
        document.getElementById('btn-volver').classList.add('show');
    </script>";
    if (ob_get_level()) ob_flush(); flush();
    echo "</body></html>"; exit;
}

// Actualizar barra y conexión exitosa
echo "<script>
    document.getElementById('dot').className           = 'dot';
    document.getElementById('msg').textContent         = 'Conexión OK · $total registros encontrados';
    document.getElementById('fill').style.width        = '5%';
    document.getElementById('pct').textContent         = '5';
    document.getElementById('status-lbl').textContent  = 'Procesando';
</script>\n";
if (ob_get_level()) ob_flush(); flush();

// Cargar catálogos desde la base de datos
echo "<script>document.getElementById('msg').textContent='Cargando catálogos…';</script>\n";
if (ob_get_level()) ob_flush(); flush();

$maquinas    = cargarCatalogo($conexion, "MAQUINAS",    "nombre_maquina",    "id_maquina");
$referencias = cargarCatalogo($conexion, "REFERENCIAS", "nombre_referencia", "id_referencia");
$colores     = cargarCatalogo($conexion, "COLORES",     "nombre_color",      "id_color");

// Contadores de resultado
$contador     = 0;
$insertados   = 0;
$actualizados = 0;
$duplicados   = 0;
$nueva_fecha  = null;

foreach ($filas as $data) {
    // Limpiar y convertir datos de cada fila
    $marca      = convertirMarca($data[0]);
    $fecha      = convertirFecha($data[1]);
    $maquina    = limpiarNombre($data[2]);
    $referencia = limpiarNombre($data[3]);
    $color      = limpiarNombre($data[4]);
    $peso_rollo = convertirNumero($data[5]);
    $retal      = convertirNumero($data[6]);

    // Obtener IDs de catálogos o crearlos si no existen
    $id_maquina    = $maquinas[$maquina]       ?? autoCrear($conexion, $maquinas,    "MAQUINAS",    "nombre_maquina",    $maquina);
    $id_referencia = $referencias[$referencia] ?? autoCrear($conexion, $referencias, "REFERENCIAS", "nombre_referencia", $referencia);
    $id_color      = $colores[$color]          ?? autoCrear($conexion, $colores,     "COLORES",     "nombre_color",      $color);

    // Modo 'todo': Insertar o actualizar si ya existe
    if ($modo === 'todo') {
        $sql = "INSERT INTO PRODUCCION_ROLLO
                    (marca_temporal,fecha_roll,id_maquina,id_referencia,
                    id_color,peso_rollo,retal_roll)
                VALUES
                    ('$marca','$fecha','$id_maquina','$id_referencia',
                    '$id_color','$peso_rollo','$retal')
                ON DUPLICATE KEY UPDATE
                    fecha_roll    = VALUES(fecha_roll),
                    id_maquina    = VALUES(id_maquina),
                    id_referencia = VALUES(id_referencia),
                    id_color      = VALUES(id_color),
                    peso_rollo    = VALUES(peso_rollo),
                    retal_roll    = VALUES(retal_roll)";
    // Modo 'nuevos': Insertar solo si no existe
    } else {
        $sql = "INSERT IGNORE INTO PRODUCCION_ROLLO
                    (marca_temporal,fecha_roll,id_maquina,id_referencia,
                    id_color,peso_rollo,retal_roll)
                VALUES
                    ('$marca','$fecha','$id_maquina','$id_referencia',
                    '$id_color','$peso_rollo','$retal')";
    }
    // Ejecutar inserción y actualizar progreso
    procesarFila($conexion, $sql, $marca, $contador, $total, $insertados, $actualizados, $duplicados, $nueva_fecha);
}

// Al finalizar: Mostrar contadores y guardar última fecha
finalizarImportacion($conexion, 'rollo', $nueva_fecha, $insertados, $actualizados, $duplicados, $total);
?>