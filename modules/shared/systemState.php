<?php
/* =====================================================
   ESTADO DEL SISTEMA (archivo JSON)
   -----------------------------------------------------
   Guarda datos operativos que no tienen tabla propia y que el panel de
   Inicio muestra al admin:
     - importaciones : última importación de cada módulo desde su Sheet
     - catalogos     : última exportación / importación de cada catálogo
     - pendientes    : caché de "registros del Sheet por importar"
   El archivo no se versiona (ver .gitignore).
===================================================== */
define('ESTADO_SISTEMA_ARCHIVO', __DIR__ . '/systemState.json');

function estadoSistemaLeer()
{
    if (!is_file(ESTADO_SISTEMA_ARCHIVO)) {
        return [];
    }
    $datos = json_decode((string) file_get_contents(ESTADO_SISTEMA_ARCHIVO), true);
    return is_array($datos) ? $datos : [];
}

// Actualizar estado con bloqueo
function estadoSistemaGuardar($grupo, $clave, array $valores)
{
    $fh = @fopen(ESTADO_SISTEMA_ARCHIVO, 'c+');
    if (!$fh) {
        return;
    }
    flock($fh, LOCK_EX);
    $datos = json_decode((string) stream_get_contents($fh), true);
    if (!is_array($datos)) {
        $datos = [];
    }
    $datos[$grupo][$clave] = array_merge($datos[$grupo][$clave] ?? [], $valores);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}
