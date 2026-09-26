<?php
/** @var mysqli $conexion */

// Importar conexion.php 
require_once dirname(__DIR__, 2) . '/includes/conexion.php';
mysqli_set_charset($conexion, "utf8mb4");
// Importar config.php
require_once dirname(__DIR__, 2) . '/includes/config.php';
// Importar importModel.php
require_once dirname(__DIR__) . '/importModel.php';

// Parametros de importación
$modo         = $_GET['modo'] ?? 'nuevos';
$ultimo_id_sheet = obtenerUltimoIdSheet($conexion, 'plana');

// Fuente de datos
$url        = "https://docs.google.com/spreadsheets/d/1DO_G6MHfoMagMMEOUOipTiE6W1UC-65f7BamJZQwGSc/export?format=csv&gid=1759801026";
$titulo     = "Producción de Maquina Plana";
$subtitulo  = "PRODUCCION_PLANA";
$volver_url = BASE_URL . "/modules/plana/views/dashboard.php";

// Leer filas del Google Sheet
[$filas, $omitidas] = leerSheet($url, $modo, $ultimo_id_sheet);
$total = count($filas);

// Importar import.php
include dirname(__DIR__) . '/views/import.php';
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

// Cargar catálogos
echo "<script>document.getElementById('msg').textContent='Cargando catálogos…';</script>\n";
if (ob_get_level()) ob_flush(); flush();

$operarios   = cargarCatalogo($conexion, "OPERARIOS",     "nombre_operario",       "id_operario");
$maquinas    = cargarCatalogo($conexion, "MAQUINAS",      "nombre_maquina",        "id_maquina");
$referencias = cargarCatalogo($conexion, "REFERENCIAS_ESP", "nombre_referencia_esp", "id_referencia_esp");

// Contadores de resultado
$contador     = 0;
$insertados   = 0;
$actualizados = 0;
$duplicados   = 0;
$nueva_fecha  = null;

foreach ($filas as $data) {
    // Limpiar datos de la fila
    $id_sheet    = trim($data[0]);
    $fecha       = convertirFecha($data[1]);
    $operario    = trim($data[2]);
    $maquina     = trim($data[3]);
    $referencia  = trim($data[4]);
    $peso_rollo  = convertirNumero($data[5]);
    $peso_retal  = convertirNumero($data[6]);
    $bultos      = (int)$data[7];
    $peso_total  = convertirNumero($data[8]);

    // IDs de catálogos (o crear)
    $id_operario       = $operarios[$operario]     ?? autoCrear($conexion, $operarios,   "OPERARIOS",   "nombre_operario",   $operario);
    $id_maquina        = $maquinas[$maquina]       ?? autoCrear($conexion, $maquinas,    "MAQUINAS",    "nombre_maquina",    $maquina);
    $id_referencia_esp = $referencias[$referencia] ?? autoCrear($conexion, $referencias, "REFERENCIAS_ESP", "nombre_referencia_esp", $referencia);

    // Modo todo: insertar/actualizar
    if ($modo === 'todo') {
        $sql = "INSERT INTO PRODUCCION_PLANA
                    (id_sheet,fecha_plana,id_operario,id_maquina,id_referencia_esp,
                    peso_rollo,peso_retal,bultos,peso_total)
                VALUES
                    ('$id_sheet','$fecha','$id_operario','$id_maquina','$id_referencia_esp',
                    '$peso_rollo','$peso_retal','$bultos','$peso_total')
                ON DUPLICATE KEY UPDATE
                    fecha_plana       = VALUES(fecha_plana),
                    id_operario       = VALUES(id_operario),
                    id_maquina        = VALUES(id_maquina),
                    id_referencia_esp = VALUES(id_referencia_esp),
                    peso_rollo        = VALUES(peso_rollo),
                    peso_retal        = VALUES(peso_retal),
                    bultos            = VALUES(bultos),
                    peso_total        = VALUES(peso_total)";
    // Modo nuevos: solo insertar
    } else {
        $sql = "INSERT IGNORE INTO PRODUCCION_PLANA
                    (id_sheet,fecha_plana,id_operario,id_maquina,id_referencia_esp,
                    peso_rollo,peso_retal,bultos,peso_total)
                VALUES
                    ('$id_sheet','$fecha','$id_operario','$id_maquina','$id_referencia_esp',
                    '$peso_rollo','$peso_retal','$bultos','$peso_total')";
    }
    // Ejecutar inserción y actualizar progreso
    procesarFila($conexion,$sql,$id_sheet,$contador,$total,$insertados,$actualizados,$duplicados,$ultimo_id_sheet);
}

// Mostrar contadores al final
finalizarImportacion($conexion,'plana',$insertados,$actualizados,$duplicados,$total,$ultimo_id_sheet);
?>