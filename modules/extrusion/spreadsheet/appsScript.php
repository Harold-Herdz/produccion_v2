<?php
// Cliente Apps Script de Extrusion

// CSV de la hoja REGISTROS
define('EXTRUSION_REGISTROS_CSV_URL', 'https://docs.google.com/spreadsheets/d/1TLsQx_s9tWBjJwuPm9xseJfsDQbKDQNQKOK9lf9Xezk/export?format=csv&gid=1284283091');

// URL /exec de Extrusión
define('EXTRUSION_APPSCRIPT_URL',   'https://script.google.com/macros/s/AKfycbynHcjlB4GF_qZ25Ng19kr7U4e3B05gu7c_CWhg9N0leRzO4G0bRMxNW237lqnj7hU3LA/exec');
define('EXTRUSION_APPSCRIPT_TOKEN', 'PLASTYPETCO_BODEGA1');

// Valida solo la forma
function appScriptConfiguradoExtrusion(){
    return strncmp(EXTRUSION_APPSCRIPT_URL, 'https://', 8) === 0
        && strlen(EXTRUSION_APPSCRIPT_URL) > 20
        && strlen(EXTRUSION_APPSCRIPT_TOKEN) >= 6;
}

// Un intento de POST al Web App
function enviarAppScriptExtrusionUnaVez($payload){
    $ctx = stream_context_create([
        'http' => [
            'method'          => 'POST',
            'header'          => "Content-Type: application/json\r\n",
            'content'         => json_encode($payload),
            'timeout'         => 90,
            'follow_location' => 1,
            'ignore_errors'   => true,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $resp = @file_get_contents(EXTRUSION_APPSCRIPT_URL, false, $ctx);
    if($resp === false){
        return ['ok' => false, 'error' => 'No se pudo conectar con Google (Apps Script).'];
    }
    $data = json_decode($resp, true);
    if(!is_array($data)){
        return ['ok' => false, 'error' => 'Respuesta inesperada del Apps Script.'];
    }
    return $data;
}

// POST al Web App con reintentos: Google a veces responde con algo que no es
// JSON aunque el turno sí haya quedado guardado. Reintentar evita mostrar un
// error cuando en realidad funcionó.
function enviarAppScriptExtrusion($payload){
    $payload['token'] = EXTRUSION_APPSCRIPT_TOKEN;
    $log = $payload['log'] ?? [];
    $operador = $payload['registros'][0][3] ?? '';

    $resp = null;
    for($intento = 1; $intento <= 2; $intento++){
        $resp = enviarAppScriptExtrusionUnaVez($payload);
        if(!empty($resp['ok'])){
            return $resp;
        }
        // Error de negocio real (no de red/formato): no tiene sentido reintentar
        if(($resp['error'] ?? '') === 'yaExportado'){
            return $resp;
        }
        // Antes de reintentar: si la respuesta fue ambigua pero los pesos ya
        // quedaron guardados, no tiene sentido enviar todo de nuevo.
        if($intento < 2 && !empty($log) && turnoYaEnRegistrosExtrusion($log['fecha'], $log['maquina'], $log['turno'], $operador)){
            return ['ok' => false, 'error' => 'yaExportado'];
        }
        if($intento < 2){ sleep(2); }
    }
    return $resp;
}

// ¿Ya quedaron guardados en REGISTROS los pesos de este turno? Se fija en las
// filas del día y la máquina, buscando fecha+máquina+turno (y opcionalmente
// operador). Sirve tanto para confirmar un envío ambiguo (con operador) como
// para saber, antes de abrir una planilla, si ese turno ya fue exportado
// alguna vez aunque la BD local no lo recuerde (con operador = null).
function turnoYaEnRegistrosExtrusion($fecha, $maquina, $turno, $operador = null){
    require_once dirname(__DIR__, 3) . '/import/importModel.php';
    $ctx = stream_context_create(['http' => ['timeout' => 20]]);
    $fh = @fopen(EXTRUSION_REGISTROS_CSV_URL, 'r', false, $ctx);
    if(!$fh){
        return false;
    }
    fgetcsv($fh, 0, ',', '"', '\\'); // encabezado
    $encontrado = false;
    while(($fila = fgetcsv($fh, 0, ',', '"', '\\')) !== false){
        if(convertirFecha($fila[1] ?? '') === $fecha
            && trim((string) ($fila[2] ?? '')) === $maquina
            && trim((string) ($fila[3] ?? '')) === $turno
            && ($operador === null || trim((string) ($fila[4] ?? '')) === $operador)
        ){
            $encontrado = true; // no se hace break: se queda con la última coincidencia
        }
    }
    fclose($fh);
    return $encontrado;
}

// Rollos ya guardados en Google para un turno (fecha+máquina+turno), en el
// mismo formato que se guarda en "filas" al finalizar. Sirve para reabrir un
// turno cuando la BD local no tiene ningún registro de él (p.ej. tras un reset).
function rollosDesdeRegistrosExtrusion($fecha, $maquina, $turno){
    require_once dirname(__DIR__, 3) . '/import/importModel.php';
    $ctx = stream_context_create(['http' => ['timeout' => 20]]);
    $fh = @fopen(EXTRUSION_REGISTROS_CSV_URL, 'r', false, $ctx);
    if(!$fh){
        return [];
    }
    fgetcsv($fh, 0, ',', '"', '\\'); // encabezado
    $rollos = [];
    while(($fila = fgetcsv($fh, 0, ',', '"', '\\')) !== false){
        if(convertirFecha($fila[1] ?? '') === $fecha
            && trim((string) ($fila[2] ?? '')) === $maquina
            && trim((string) ($fila[3] ?? '')) === $turno
        ){
            $rollos[] = [
                'referencia' => trim((string) ($fila[5] ?? '')),
                'color'      => trim((string) ($fila[6] ?? '')),
                'peso'       => (float) str_replace(',', '.', (string) ($fila[7] ?? '0')),
                'lamina'     => trim((string) ($fila[8] ?? '')),
            ];
        }
    }
    fclose($fh);
    return $rollos;
}
