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
$ultimo_id_sheet = obtenerUltimoIdSheet($conexion, 'sellado');

// Fuente de datos
$url        = "https://docs.google.com/spreadsheets/d/1B1A-pSUBLG9w56ibWcERxhAKEsaPJN74SjRjeNv2UCg/export?format=csv&gid=1191265238";      
$titulo     = "Producción de Sellado";
$subtitulo  = "PRODUCCION_SELLADO";
$volver_url = BASE_URL . "/modules/sellado/views/dashboard.php";

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

// Cargar catálogos desde la base de datos
echo "<script>document.getElementById('msg').textContent='Cargando catálogos…';</script>\n";
if (ob_get_level()) ob_flush(); flush();

$maquinas    = cargarCatalogo($conexion, "MAQUINAS",    "nombre_maquina",    "id_maquina");
$operarios   = cargarCatalogo($conexion, "OPERARIOS",   "nombre_operario",   "id_operario");
$turnos      = cargarCatalogo($conexion, "TURNOS",      "nombre_turno",      "id_turno");
$referencias = cargarCatalogo($conexion, "REFERENCIAS", "nombre_referencia", "id_referencia");
$colores     = cargarCatalogo($conexion, "COLORES",     "nombre_color",      "id_color");

// Contadores de resultado
$contador     = 0;
$insertados   = 0;
$actualizados = 0;
$duplicados   = 0;

foreach ($filas as $data) {
    // Limpiar y convertir datos de cada fila
    $id_sheet   = trim($data[0]);
    $fecha      = convertirFecha($data[1]);
    $turno      = $data[2];
    $maquina    = limpiarNombre($data[3]);
    $operario   = limpiarNombre($data[4]);
    $jornada    = limpiarNombre($data[5]);
    $referencia = limpiarNombre($data[6]);
    $color      = limpiarNombre($data[7]);
    $paq_x70    = (int)$data[8];
    $paq_x90    = (int)$data[9];
    $paq_x98    = (int)$data[10];
    $peso_h1    = ($data[11] === "") ? null : convertirNumero($data[11]);
    $peso_h2    = ($data[12] === "") ? null : convertirNumero($data[12]);
    $peso_h3    = ($data[13] === "") ? null : convertirNumero($data[13]);
    $peso_h4    = ($data[14] === "") ? null : convertirNumero($data[14]);
    $peso_h5    = ($data[15] === "") ? null : convertirNumero($data[15]);
    $obs_sellado        = mysqli_real_escape_string($conexion, $data[16]);

    // Obtener IDs de catálogos o crearlos si no existen
    $id_maquina    = $maquinas[$maquina]       ?? autoCrear($conexion, $maquinas,    "MAQUINAS",    "nombre_maquina",    $maquina);
    $id_operario   = $operarios[$operario]     ?? autoCrear($conexion, $operarios,   "OPERARIOS",   "nombre_operario",   $operario);
    $id_referencia = $referencias[$referencia] ?? autoCrear($conexion, $referencias, "REFERENCIAS", "nombre_referencia", $referencia);
    $id_color      = $colores[$color]          ?? autoCrear($conexion, $colores,     "COLORES",     "nombre_color",      $color);

    // Por si el peso es NULL
    $peso1 = is_null($peso_h1) ? "NULL" : $peso_h1;
    $peso2 = is_null($peso_h2) ? "NULL" : $peso_h2;
    $peso3 = is_null($peso_h3) ? "NULL" : $peso_h3;
    $peso4 = is_null($peso_h4) ? "NULL" : $peso_h4;
    $peso5 = is_null($peso_h5) ? "NULL" : $peso_h5;

    // Convertir el turno en bloque horario
    $bloque = convertirBloque($turno);
    $keyTurno = $bloque . '|' . $jornada;

    if (isset($turnos[$keyTurno])) {
        $id_turno = $turnos[$keyTurno];
    } else {
        mysqli_query($conexion, "
            INSERT INTO TURNOS (bloque_horario, jornada)
            VALUES ('$bloque', '$jornada')
        ");

        $id_turno = mysqli_insert_id($conexion);
        $turnos[$keyTurno] = $id_turno;
    }
    // Modo 'todo': Insertar o actualizar si ya existe
    if ($modo === 'todo') {
        $sql = "INSERT INTO PRODUCCION_SELLADO
                    (id_sheet,fecha_sellado,id_maquina,id_operario,id_turno,
                    id_referencia,id_color,paquetes_x70,paquetes_x90,paquetes_x98,
                    peso_hora1,peso_hora2,peso_hora3,peso_hora4,peso_hora5,obs_sellado)
                VALUES
                    ('$id_sheet','$fecha','$id_maquina','$id_operario','$id_turno',
                    '$id_referencia','$id_color','$paq_x70','$paq_x90','$paq_x98', 
                    $peso1, $peso2, $peso3, $peso4, $peso5,'$obs_sellado')
                ON DUPLICATE KEY UPDATE
                    fecha_sellado   = VALUES(fecha_sellado),
                    id_maquina      = VALUES(id_maquina),
                    id_operario     = VALUES(id_operario),
                    id_turno        = VALUES(id_turno),
                    id_referencia   = VALUES(id_referencia),
                    id_color        = VALUES(id_color),
                    paquetes_x70    = VALUES(paquetes_x70),
                    paquetes_x90    = VALUES(paquetes_x90),
                    paquetes_x98    = VALUES(paquetes_x98),
                    peso_hora1      = VALUES(peso_hora1),
                    peso_hora2      = VALUES(peso_hora2),
                    peso_hora3      = VALUES(peso_hora3),
                    peso_hora4      = VALUES(peso_hora4),
                    peso_hora5      = VALUES(peso_hora5),
                    obs_sellado     = VALUES(obs_sellado)";
    // Modo 'nuevos': Insertar solo si no existe
    } else {
        $sql = "INSERT IGNORE INTO PRODUCCION_SELLADO
                    (id_sheet,fecha_sellado,id_maquina,id_operario,id_turno,
                    id_referencia,id_color,paquetes_x70,paquetes_x90,paquetes_x98,
                    peso_hora1,peso_hora2,peso_hora3,peso_hora4,peso_hora5,obs_sellado)
                VALUES
                    ('$id_sheet','$fecha','$id_maquina','$id_operario','$id_turno',
                    '$id_referencia','$id_color','$paq_x70','$paq_x90','$paq_x98', 
                    $peso1, $peso2, $peso3, $peso4, $peso5,'$obs_sellado')";
    }
    // Ejecutar inserción y actualizar progreso
    procesarFila($conexion,$sql,$id_sheet,$contador,$total,$insertados,$actualizados,$duplicados,$ultimo_id_sheet);
}

// Al finalizar: Mostrar contadores y guardar última fecha
finalizarImportacion($conexion,'sellado',$insertados,$actualizados,$duplicados,$total,$ultimo_id_sheet);
?>