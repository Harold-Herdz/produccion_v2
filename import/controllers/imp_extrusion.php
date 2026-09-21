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
$ultimo_id_sheet = obtenerUltimoIdSheet($conexion, 'extrusion');

// Fuente de datos
$url = "https://docs.google.com/spreadsheets/d/1TLsQx_s9tWBjJwuPm9xseJfsDQbKDQNQKOK9lf9Xezk/export?format=csv&gid=1583688034";
$titulo     = "Producción de Extrusión";
$subtitulo  = "PRODUCCION_EXTRUSION";
$volver_url = BASE_URL . "/modules/extrusion/views/dashboard.php";

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
$turnos      = cargarCatalogo($conexion, "TURNOS",      "nombre_turno",      "id_turno");
$operadores  = cargarCatalogo($conexion, "OPERADORES",  "nombre_operador",   "id_operador");
$referencias = cargarCatalogo($conexion, "REFERENCIAS", "nombre_referencia", "id_referencia");
$colores     = cargarCatalogo($conexion, "COLORES",     "nombre_color",      "id_color");
$laminas     = cargarCatalogo($conexion, "LAMINA_P",    "nombre_lamina_p",   "id_lamina_p");

// Contadores de resultado
$contador     = 0;
$insertados   = 0;
$actualizados = 0;
$duplicados   = 0;

foreach ($filas as $data) {
    // Limpiar y convertir datos de cada fila
    $id_sheet   = trim($data[0]);
    $fecha      = convertirFecha($data[1]);
    $maquina    = limpiarNombre($data[2]);
    $turno      = limpiarNombre($data[3]);
    $operador   = limpiarNombre($data[4]);
    $referencia = limpiarNombre($data[5]);
    $color      = limpiarNombre($data[6]);
    $lamina     = limpiarNombre($data[7]);
    $rollos     = convertirNumero($data[8]);
    $peso_total = convertirNumero($data[9]);

    // Turno: catálogo cerrado de 4 valores (Día/Tarde/Noche/18 Horas), nunca se crea aquí.
    // Si no se reconoce o el catálogo aún no lo tiene, la fila se guarda igual con el
    // turno vacío (NULL) en vez de perderse; al llenar el catálogo y reimportar se completa solo.
    // El Sheet trae "Día"/"Tarde"/"Noche"/"18 Horas"
    $mapaTurnoExt = ['dia' => 'Día', 'día' => 'Día', 'tarde' => 'Tarde', 'noche' => 'Noche', '18 horas' => '18 Horas'];
    $nombreTurno = $mapaTurnoExt[strtolower(trim($turno))] ?? null;
    $id_turno = ($nombreTurno !== null) ? ($turnos[$nombreTurno] ?? null) : null;
    $id_turno_sql = ($id_turno === null) ? "NULL" : "'{$id_turno}'";
    if ($id_turno === null) {
        $logMsg = addslashes("⚠ Turno no reconocido «{$turno}» · {$id_sheet}: guardado sin turno");
        echo "<script>tick($contador,$total,$insertados,$actualizados,$duplicados,'$logMsg','dup');</script>\n";
        if (ob_get_level()) ob_flush();
        flush();
    }

    // Obtener IDs de catálogos o crearlos si no existen
    $id_maquina    = $maquinas[$maquina]       ?? autoCrear($conexion, $maquinas,    "MAQUINAS",    "nombre_maquina",    $maquina);
    $id_operador   = $operadores[$operador]    ?? autoCrear($conexion, $operadores,  "OPERADORES",  "nombre_operador",   $operador);
    $id_referencia = $referencias[$referencia] ?? autoCrear($conexion, $referencias, "REFERENCIAS", "nombre_referencia", $referencia);
    $id_color      = $colores[$color]          ?? autoCrear($conexion, $colores,     "COLORES",     "nombre_color",      $color);
    $id_lamina_p   = $laminas[$lamina]         ?? autoCrear($conexion, $laminas,     "LAMINA_P",    "nombre_lamina_p",   $lamina);

    // Modo 'todo': Insertar o actualizar si ya existe
    if ($modo === 'todo') {
        $sql = "INSERT INTO PRODUCCION_EXTRUSION
                    (id_sheet,fecha_extrusion,id_maquina,id_turno,id_operador,
                    id_referencia,id_color,id_lamina_p,rollos,peso_total)
                VALUES
                    ('$id_sheet','$fecha','$id_maquina',$id_turno_sql,'$id_operador',
                    '$id_referencia','$id_color','$id_lamina_p','$rollos','$peso_total')
                ON DUPLICATE KEY UPDATE
                    fecha_extrusion = VALUES(fecha_extrusion),
                    id_maquina      = VALUES(id_maquina),
                    id_turno        = VALUES(id_turno),
                    id_operador     = VALUES(id_operador),
                    id_referencia   = VALUES(id_referencia),
                    id_color        = VALUES(id_color),
                    id_lamina_p     = VALUES(id_lamina_p),
                    rollos          = VALUES(rollos),
                    peso_total      = VALUES(peso_total)";
    // Modo 'nuevos': Insertar solo si no existe
    } else {
        $sql = "INSERT IGNORE INTO PRODUCCION_EXTRUSION
                    (id_sheet,fecha_extrusion,id_maquina,id_turno,id_operador,
                    id_referencia,id_color,id_lamina_p,rollos,peso_total)
                VALUES
                    ('$id_sheet','$fecha','$id_maquina',$id_turno_sql,'$id_operador',
                    '$id_referencia','$id_color','$id_lamina_p','$rollos','$peso_total')";
    }
    // Ejecutar inserción y actualizar progreso
    procesarFila($conexion,$sql,$id_sheet,$contador,$total,$insertados,$actualizados,$duplicados,$ultimo_id_sheet);
}

// Al finalizar: Mostrar contadores y guardar última fecha
finalizarImportacion($conexion,'extrusion',$insertados,$actualizados,$duplicados,$total,$ultimo_id_sheet);
?>