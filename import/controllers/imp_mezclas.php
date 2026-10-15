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
$modo            = $_GET['modo'] ?? 'nuevos';
$ultimo_id_sheet = obtenerUltimoIdSheet($conexion, 'mezclas');

// Fuente de datos
$url        = "https://docs.google.com/spreadsheets/d/10JvKK9SVouBV5qo7PUt23aQDbf-GWGU-YdcQ0LkquC8/export?format=csv&gid=0";
$titulo     = "Producción de Mezclas";
$subtitulo  = "PRODUCCION_MEZCLA";
$volver_url = BASE_URL . "/modules/mezclas/views/dashboard.php";

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

$maquinas       = cargarCatalogo($conexion, "MAQUINAS", "nombre_maquina", "id_maquina");
$operarios      = cargarCatalogo($conexion, "OPERARIOS", "nombre_operario", "id_operario");
$referencias    = cargarCatalogo($conexion, "REFERENCIAS", "nombre_referencia", "id_referencia");
$referenciasEsp = cargarCatalogo($conexion, "REFERENCIAS_ESP", "nombre_referencia_esp", "id_referencia_esp");

// Columnas numéricas (nombre => índice en la fila del CSV). Las "_r",
// ORIGINAL B, FG, MASTER y LINEAL vienen con sufijo "x25"; el resto (kg) son
// números planos.
$columnasX25 = [
    'blanco_r' => 5, 'negro_r' => 6, 'rojo_r' => 7, 'amarillo_r' => 8, 'azul_r' => 9, 'verde_r' => 10,
    'naranja_r' => 11, 'marron_r' => 12, 'ladrillo_r' => 13, 'original_b' => 14, 'fg' => 15, 'master' => 16, 'lineal' => 17,
];
$columnasKg = [
    'deshidratante' => 18,
    'p_blanco' => 20, 'p_negro' => 21, 'p_rojo' => 22, 'p_amarillo' => 23, 'p_azul' => 24,
    'p_verde' => 25, 'p_naranja' => 26, 'p_marron' => 27, 'p_ladrillo' => 28,
];

// Número de la hoja (puede traer "x25" de sufijo) a float o null si viene vacío
function convertirNumeroMezclaImport($valor){
    $valor = trim((string) $valor);
    if($valor === ''){ return null; }
    $valor = preg_replace('/x25$/i', '', $valor);
    return convertirNumero($valor);
}

// Contadores de resultado
$contador     = 0;
$insertados   = 0;
$actualizados = 0;
$duplicados   = 0;
$omitidos     = 0;

foreach ($filas as $data) {
    // Limpiar datos de la fila
    $id_sheet   = trim($data[0]);
    $fecha      = convertirFecha($data[1]);
    $maquina    = limpiarNombre($data[2]);
    $operario   = limpiarNombre($data[3]);
    $referencia = limpiarNombre($data[4] ?? '');
    $obs        = mysqli_real_escape_string($conexion, trim((string) ($data[19] ?? '')));

    // Máquina: catálogo cerrado, si no coincide se omite la fila
    $id_maquina = idCatalogoCerrado($maquinas, $maquina);
    if ($id_maquina === null) {
        avisarFilaOmitida($contador, $total, $insertados, $actualizados, $duplicados, $omitidos, $id_sheet, "máquina «{$maquina}» no existe en el catálogo");
        continue;
    }

    // Operario: opcional, pendiente de revisión si es nuevo
    $id_operario = idCatalogoPendienteImport($conexion, $operarios, "OPERARIOS", "nombre_operario", $operario, 'nuevo operario', 'mezclas');

    // Referencia: catálogo cerrado (REFERENCIAS). Si el texto no coincide con
    // una ya existente, SIEMPRE se resuelve/crea en REFERENCIAS_ESP, nunca aquí.
    $id_referencia = $referencias[$referencia] ?? null;
    $id_referencia_esp = null;
    if ($id_referencia === null && trim((string) $referencia) !== '') {
        $id_referencia_esp = idCatalogoPendienteImport($conexion, $referenciasEsp, "REFERENCIAS_ESP", "nombre_referencia_esp", $referencia, 'nueva referencia especial', 'mezclas');
    }

    $valores = [];
    foreach($columnasX25 as $col => $idx){ $valores[$col] = convertirNumeroMezclaImport($data[$idx] ?? ''); }
    foreach($columnasKg as $col => $idx){ $valores[$col] = convertirNumeroMezclaImport($data[$idx] ?? ''); }

    $idOperarioSql   = ($id_operario === null) ? "NULL" : "'{$id_operario}'";
    $idReferenciaSql    = ($id_referencia === null) ? "NULL" : "'{$id_referencia}'";
    $idReferenciaEspSql = ($id_referencia_esp === null) ? "NULL" : "'{$id_referencia_esp}'";

    $columnas = array_keys($valores);
    $valoresSql = array_map(fn($v) => $v === null ? "NULL" : "'{$v}'", $valores);
    $listaColumnas = implode(',', $columnas);
    $listaValores  = implode(',', $valoresSql);

    // Modo todo: insertar/actualizar
    if ($modo === 'todo') {
        $setClauses = array_map(fn($c) => "$c = VALUES($c)", $columnas);
        $sql = "INSERT INTO PRODUCCION_MEZCLA
                    (id_sheet,fecha_mezcla,id_maquina,id_operario,id_referencia,id_referencia_esp,observaciones,{$listaColumnas})
                VALUES
                    ('$id_sheet','$fecha','$id_maquina',$idOperarioSql,$idReferenciaSql,$idReferenciaEspSql,'$obs',{$listaValores})
                ON DUPLICATE KEY UPDATE
                    fecha_mezcla      = VALUES(fecha_mezcla),
                    id_maquina        = VALUES(id_maquina),
                    id_operario       = VALUES(id_operario),
                    id_referencia     = VALUES(id_referencia),
                    id_referencia_esp = VALUES(id_referencia_esp),
                    observaciones     = VALUES(observaciones),
                    " . implode(', ', $setClauses);
    // Modo nuevos: solo insertar
    } else {
        $sql = "INSERT IGNORE INTO PRODUCCION_MEZCLA
                    (id_sheet,fecha_mezcla,id_maquina,id_operario,id_referencia,id_referencia_esp,observaciones,{$listaColumnas})
                VALUES
                    ('$id_sheet','$fecha','$id_maquina',$idOperarioSql,$idReferenciaSql,$idReferenciaEspSql,'$obs',{$listaValores})";
    }
    // Ejecutar inserción y actualizar progreso
    procesarFila($conexion,$sql,$id_sheet,$contador,$total,$insertados,$actualizados,$duplicados,$omitidos,$ultimo_id_sheet);
}

// Mostrar contadores al final
finalizarImportacion($conexion,'mezclas',$insertados,$actualizados,$duplicados,$omitidos,$total,$ultimo_id_sheet);
?>
