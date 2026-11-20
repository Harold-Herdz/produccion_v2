<?php
// Nunca mostrar errores crudos; registrar en archivo
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$dirLogs = dirname(__DIR__) . '/logs';
if (!is_dir($dirLogs)) { @mkdir($dirLogs, 0755, true); }
if (is_dir($dirLogs)) { ini_set('error_log', $dirLogs . '/php-errors.log'); }

// ¿Esta respuesta ya es JSON?
function esRespuestaJson(){
    foreach (headers_list() as $h) {
        if (stripos($h, 'Content-Type:') === 0 && stripos($h, 'application/json') !== false) {
            return true;
        }
    }
    return false;
}
// Página o JSON amigable, nunca el error crudo
function responderErrorAmigable(){
    while (ob_get_level()) { @ob_end_clean(); }
    if (esRespuestaJson()) {
        if (!headers_sent()) { header('Content-Type: application/json'); }
        echo json_encode(['ok' => false, 'error' => 'Ocurrió un error inesperado. Intenta de nuevo.']);
        return;
    }
    require __DIR__ . '/errorPage.php';
}
set_exception_handler(function ($e) {
    error_log('Excepción no capturada: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    responderErrorAmigable();
});
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) { return false; }
    error_log($errstr . ' en ' . $errfile . ':' . $errline);
    return true; // seguir ejecutando: no detener por avisos
});
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('Fatal: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line']);
        responderErrorAmigable();
    }
});

// Variables de la BD
if ($_SERVER['HTTP_HOST'] === 'localhost') {
    $host     = "localhost";
    $usuario  = "root";
    $password = "";
    $database = "BD_PRODUCCION_V2";
} else {
    $host     = "sql301.infinityfree.com";
    $usuario  = "if0_42142771";
    $password = "plastypet12";
    $database = "if0_42142771_produccion";
}

// Conectar a la BD
$conexion = mysqli_connect($host, $usuario, $password, $database);

// Verificar conexión
if (!$conexion) {
    error_log('Conexión a la BD falló: ' . mysqli_connect_error());
    responderErrorAmigable();
    exit;
}

// Establecer codificación de caracteres
mysqli_set_charset($conexion, "utf8mb4");
?>