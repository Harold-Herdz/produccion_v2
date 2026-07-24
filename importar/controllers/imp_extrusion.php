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
$ultimo_id_sheet = obtenerUltimoIdSheet($conexion, 'extrusion');

// Fuente de datos
$url = "https://docs.google.com/spreadsheets/d/1TLsQx_s9tWBjJwuPm9xseJfsDQbKDQNQKOK9lf9Xezk/export?format=csv&gid=1284283091";
$titulo     = "Producción de Extrusión";
$subtitulo  = "PRODUCCION_EXTRUSION";
$volver_url = BASE_URL . "/modules/extrusion/views/dashboard.php";

// Leer filas del Google Sheet
[$filas, $omitidas] = leerSheet($url, $modo, $ultimo_id_sheet);
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

$maquinas       = cargarCatalogo($conexion, "MAQUINAS",            "nombre_maquina",       "id_maquina");
$turnos_ext     = cargarCatalogo($conexion, "TURNOS_EXTRUSION",    "nombre_turno_ext",     "id_turno_ext");
$operadores_ext = cargarCatalogo($conexion, "OPERADORES_EXTRUSION","nombre_operador_ext",  "id_operador_ext");
$referencias    = cargarCatalogo($conexion, "REFERENCIAS",         "nombre_referencia",    "id_referencia");
$colores        = cargarCatalogo($conexion, "COLORES",             "nombre_color",         "id_color");

// Contadores de resultado
$contador     = 0;
$insertados   = 0;
$actualizados = 0;
$duplicados   = 0;

foreach ($filas as $data) {
    // Limpiar y convertir datos de cada fila
    $id_sheet       = trim($data[0]);
    $fecha          = convertirFecha($data[1]);
    $maquina        = limpiarNombre($data[2]);
    $turno_ext      = limpiarNombre($data[3]);
    $operador_ext   = limpiarNombre($data[4]);
    $referencia     = limpiarNombre($data[5]);
    $color          = limpiarNombre($data[6]);
    $peso_rollo     = convertirNumero($data[7]);
    $lamina_p       = limpiarNombre($data[8]);

    // Obtener IDs de catálogos o crearlos si no existen
    $id_maquina      = $maquinas[$maquina]            ?? autoCrear($conexion, $maquinas,       "MAQUINAS",             "nombre_maquina",      $maquina);
    $id_turno_ext    = $turnos_ext[$turno_ext]        ?? autoCrear($conexion, $turnos_ext,     "TURNOS_EXTRUSION",     "nombre_turno_ext",    $turno_ext);
    $id_operador_ext = $operadores_ext[$operador_ext] ?? autoCrear($conexion, $operadores_ext, "OPERADORES_EXTRUSION", "nombre_operador_ext", $operador_ext);
    $id_referencia   = $referencias[$referencia]      ?? autoCrear($conexion, $referencias,    "REFERENCIAS",          "nombre_referencia",   $referencia);
    $id_color        = $colores[$color]               ?? autoCrear($conexion, $colores,        "COLORES",              "nombre_color",        $color);

    // Modo 'todo': Insertar o actualizar si ya existe
    if ($modo === 'todo') {
        $sql = "INSERT INTO PRODUCCION_EXTRUSION
                    (id_sheet,fecha_ext,id_maquina,id_turno_ext,id_operador_ext,
                    id_referencia,id_color,peso_rollo,lamina_p)
                VALUES
                    ('$id_sheet','$fecha','$id_maquina','$id_turno_ext','$id_operador_ext',
                    '$id_referencia','$id_color','$peso_rollo','$lamina_p')
                ON DUPLICATE KEY UPDATE
                    fecha_ext       = VALUES(fecha_ext),
                    id_maquina      = VALUES(id_maquina),
                    id_turno_ext    = VALUES(id_turno_ext),
                    id_operador_ext = VALUES(id_operador_ext),
                    id_referencia   = VALUES(id_referencia),
                    id_color        = VALUES(id_color),
                    peso_rollo      = VALUES(peso_rollo),
                    lamina_p        = VALUES(lamina_p)";
    // Modo 'nuevos': Insertar solo si no existe
    } else {
        $sql = "INSERT IGNORE INTO PRODUCCION_EXTRUSION
                    (id_sheet,fecha_ext,id_maquina,id_turno_ext,id_operador_ext,
                    id_referencia,id_color,peso_rollo,lamina_p)
                VALUES
                    ('$id_sheet','$fecha','$id_maquina','$id_turno_ext','$id_operador_ext',
                    '$id_referencia','$id_color','$peso_rollo','$lamina_p')";
    }
    // Ejecutar inserción y actualizar progreso
    procesarFila($conexion,$sql,$id_sheet,$contador,$total,$insertados,$actualizados,$duplicados,$ultimo_id_sheet);
}

// Al finalizar: Mostrar contadores y guardar última fecha
finalizarImportacion($conexion,'extrusion',$insertados,$actualizados,$duplicados,$total,$ultimo_id_sheet);
?>