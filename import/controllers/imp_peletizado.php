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
$ultimo_id_sheet = obtenerUltimoIdSheet($conexion, 'peletizado');

// Fuente de datos
$url = "https://docs.google.com/spreadsheets/d/1GDmQDnOrJGoipBT5Cd26uYwd6My32YIJBZOpoDn5jEY/export?format=csv&gid=1650985044";
$titulo     = "Producción de Peletizado";
$subtitulo  = "PRODUCCION_PELETIZADO";
$volver_url = BASE_URL . "/modules/peletizado/views/dashboard.php";

// Leer filas del Google Sheet
[$filas, $omitidas] = leerSheet($url, $modo, $ultimo_id_sheet);
$total = is_array($filas) ? count($filas) : 0;

// Importar import.php
include dirname(__DIR__) . '/views/import.php';
?>

<?php
// No se pudo conectar al Sheet (p.ej. no está compartido públicamente)
if ($filas === null) {
    echo "<script>
        document.getElementById('dot').className          = 'dot error';
        document.getElementById('msg').textContent        = 'No se pudo leer el Sheet. Revisa que esté compartido como \'Cualquiera con el enlace: Lector\'.';
        document.getElementById('status-lbl').textContent = 'Error de conexión';
        document.getElementById('btn-volver').classList.add('show');
    </script>";
    if (ob_get_level()) ob_flush(); flush();
    echo "</body></html>"; exit;
}

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

$maquinas  = cargarCatalogo($conexion, "MAQUINAS",  "nombre_maquina",  "id_maquina");
$turnos    = cargarCatalogo($conexion, "TURNOS",    "nombre_turno",    "id_turno");
$operarios = cargarCatalogo($conexion, "OPERARIOS", "nombre_operario", "id_operario");
$colores   = cargarCatalogo($conexion, "COLORES",   "nombre_color",    "id_color");

// Contadores de resultado
$contador     = 0;
$insertados   = 0;
$actualizados = 0;
$duplicados   = 0;

foreach ($filas as $data) {
    // Limpiar datos de la fila
    $id_sheet    = trim($data[0]);
    $fecha       = convertirFecha($data[1]);
    $maquina     = limpiarNombre($data[2]);
    $turno       = limpiarNombre($data[3]);
    $operario1   = limpiarNombre($data[4]);
    $operario2   = limpiarNombre($data[5]);
    $color       = limpiarNombre($data[6]);
    $alta_retal  = (int) convertirNumero($data[7]);
    $baja        = (int) convertirNumero($data[8]);
    $refiltrado  = (int) convertirNumero($data[9]);
    $soplado     = (int) convertirNumero($data[10]);
    $torta       = (int) convertirNumero($data[11]);
    $limpieza    = (int) convertirNumero($data[12]);
    $total_cat   = (int) convertirNumero($data[13]);
    $obs         = mysqli_real_escape_string($conexion, trim((string) ($data[14] ?? '')));

    // IDs de catálogos (o crear). Operario 2 y color son opcionales.
    $id_maquina  = $maquinas[$maquina] ?? autoCrear($conexion, $maquinas, "MAQUINAS", "nombre_maquina", $maquina);
    $id_turno    = $turnos[$turno]     ?? autoCrear($conexion, $turnos,   "TURNOS",   "nombre_turno",   $turno);
    $id_operario = $operarios[$operario1] ?? autoCrear($conexion, $operarios, "OPERARIOS", "nombre_operario", $operario1);
    $id_operario2 = ($operario2 === '')
        ? null
        : ($operarios[$operario2] ?? autoCrear($conexion, $operarios, "OPERARIOS", "nombre_operario", $operario2));
    $id_color = ($color === '')
        ? null
        : ($colores[$color] ?? autoCrear($conexion, $colores, "COLORES", "nombre_color", $color));

    $id_operario2_sql = ($id_operario2 === null) ? "NULL" : "'{$id_operario2}'";
    $id_color_sql      = ($id_color === null) ? "NULL" : "'{$id_color}'";

    // Modo todo: insertar/actualizar
    if ($modo === 'todo') {
        $sql = "INSERT INTO PRODUCCION_PELETIZADO
                    (id_sheet,fecha_peletizado,id_maquina,id_turno,id_operario,id_operario2,id_color,
                    alta_retal,baja,refiltrado,soplado,torta,limpieza,total,obs_peletizado)
                VALUES
                    ('$id_sheet','$fecha','$id_maquina','$id_turno','$id_operario',$id_operario2_sql,$id_color_sql,
                    '$alta_retal','$baja','$refiltrado','$soplado','$torta','$limpieza','$total_cat','$obs')
                ON DUPLICATE KEY UPDATE
                    fecha_peletizado = VALUES(fecha_peletizado),
                    id_maquina       = VALUES(id_maquina),
                    id_turno         = VALUES(id_turno),
                    id_operario      = VALUES(id_operario),
                    id_operario2     = VALUES(id_operario2),
                    id_color         = VALUES(id_color),
                    alta_retal       = VALUES(alta_retal),
                    baja             = VALUES(baja),
                    refiltrado       = VALUES(refiltrado),
                    soplado          = VALUES(soplado),
                    torta            = VALUES(torta),
                    limpieza         = VALUES(limpieza),
                    total            = VALUES(total),
                    obs_peletizado   = VALUES(obs_peletizado)";
    // Modo nuevos: solo insertar
    } else {
        $sql = "INSERT IGNORE INTO PRODUCCION_PELETIZADO
                    (id_sheet,fecha_peletizado,id_maquina,id_turno,id_operario,id_operario2,id_color,
                    alta_retal,baja,refiltrado,soplado,torta,limpieza,total,obs_peletizado)
                VALUES
                    ('$id_sheet','$fecha','$id_maquina','$id_turno','$id_operario',$id_operario2_sql,$id_color_sql,
                    '$alta_retal','$baja','$refiltrado','$soplado','$torta','$limpieza','$total_cat','$obs')";
    }
    // Ejecutar inserción y actualizar progreso
    procesarFila($conexion,$sql,$id_sheet,$contador,$total,$insertados,$actualizados,$duplicados,$ultimo_id_sheet);
}

// Mostrar contadores al final
finalizarImportacion($conexion,'peletizado',$insertados,$actualizados,$duplicados,$total,$ultimo_id_sheet);
?>
