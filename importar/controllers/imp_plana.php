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
$ultima_fecha = obtenerUltimaFecha($conexion, 'plana');

// Fuente de datos
$url        = "https://docs.google.com/spreadsheets/d/1DO_G6MHfoMagMMEOUOipTiE6W1UC-65f7BamJZQwGSc/export?format=csv&gid=201213148";
$titulo     = "Producción de Maquina Plana";
$subtitulo  = "PRODUCCION_PLANA";
$volver_url = BASE_URL . "/modules/plana/views/dashboard.php";

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

$operarios   = cargarCatalogo($conexion, "OPERARIOS",   "nombre_operario",   "id_operario");
$maquinas    = cargarCatalogo($conexion, "MAQUINAS",    "nombre_maquina",    "id_maquina");
$referencias = cargarCatalogo($conexion, "REFERENCIAS", "nombre_referencia", "id_referencia");
$turnos      = cargarCatalogo($conexion, "TURNOS",      "nombre_turno",      "id_turno");

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
    $operario   = trim($data[2]);
    $maquina    = trim($data[3]);
    $referencia = trim($data[4]);
    $turno      = trim($data[5]);
    $peso       = convertirNumero($data[6]);
    $bultos     = (int)$data[7];
    $retal      = convertirNumero($data[8]);
    $total_prod = convertirNumero($data[9]);

    // Obtener IDs de catálogos o crearlos si no existen
    $id_operario   = $operarios[$operario]     ?? autoCrear($conexion, $operarios,   "OPERARIOS",   "nombre_operario",   $operario);
    $id_maquina    = $maquinas[$maquina]       ?? autoCrear($conexion, $maquinas,    "MAQUINAS",    "nombre_maquina",    $maquina);
    $id_referencia = $referencias[$referencia] ?? autoCrear($conexion, $referencias, "REFERENCIAS", "nombre_referencia", $referencia);
    $id_turno      = $turnos[$turno]           ?? autoCrear($conexion, $turnos,      "TURNOS",      "nombre_turno",      $turno);

    // Modo 'todo': Insertar o actualizar si ya existe
    if ($modo === 'todo') {
        $sql = "INSERT INTO PRODUCCION_PLANA
                    (marca_temporal,fecha_plana,id_operario,id_maquina,id_referencia,
                    id_turno,peso_plana,bultos_plana,retal_plana,total_plana)
                VALUES
                    ('$marca','$fecha','$id_operario','$id_maquina','$id_referencia',
                    '$id_turno','$peso','$bultos','$retal','$total_prod')
                ON DUPLICATE KEY UPDATE
                    fecha_plana   = VALUES(fecha_plana),
                    id_operario   = VALUES(id_operario),
                    id_maquina    = VALUES(id_maquina),
                    id_referencia = VALUES(id_referencia),
                    id_turno      = VALUES(id_turno),
                    peso_plana    = VALUES(peso_plana),
                    bultos_plana  = VALUES(bultos_plana),
                    retal_plana   = VALUES(retal_plana),
                    total_plana   = VALUES(total_plana)";
    // Modo 'nuevos': Insertar solo si no existe
    } else {
        $sql = "INSERT IGNORE INTO PRODUCCION_PLANA
                    (marca_temporal,fecha_plana,id_operario,id_maquina,id_referencia,
                    id_turno,peso_plana,bultos_plana,retal_plana,total_plana)
                VALUES
                    ('$marca','$fecha','$id_operario','$id_maquina','$id_referencia',
                    '$id_turno','$peso','$bultos','$retal','$total_prod')";
    }
    // Ejecutar inserción y actualizar progreso
    procesarFila($conexion, $sql, $marca, $contador, $total, $insertados, $actualizados, $duplicados, $nueva_fecha);
}

// Al finalizar: Mostrar contadores y guardar última fecha
finalizarImportacion($conexion, 'plana', $nueva_fecha, $insertados, $actualizados, $duplicados, $total);
?>