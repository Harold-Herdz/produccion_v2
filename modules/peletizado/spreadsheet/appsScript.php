<?php
// Cliente Apps Script de Peletizado

// CSV de la hoja REGISTROS
define('PELETIZADO_REGISTROS_CSV_URL', 'https://docs.google.com/spreadsheets/d/1GDmQDnOrJGoipBT5Cd26uYwd6My32YIJBZOpoDn5jEY/export?format=csv&gid=1650985044');

// URL /exec del Web App de Peletizado
define('PELETIZADO_APPSCRIPT_URL',   'https://script.google.com/macros/s/AKfycbyVqSq52b0kBh3ft-xQuLLDzUBFczAOYsSgQ4XqdpFHiCeX1LdJHyOh-jsVo31pWxPK/exec');
define('PELETIZADO_APPSCRIPT_TOKEN', 'PLASTYPETCO_PELETIZADO');

// Valida solo la forma
function appScriptConfiguradoPeletizado(){
    return strncmp(PELETIZADO_APPSCRIPT_URL, 'https://', 8) === 0
        && strlen(PELETIZADO_APPSCRIPT_URL) > 20
        && strlen(PELETIZADO_APPSCRIPT_TOKEN) >= 6;
}

// Un intento de POST al Web App
function enviarAppScriptPeletizadoUnaVez($payload){
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

    $resp = @file_get_contents(PELETIZADO_APPSCRIPT_URL, false, $ctx);
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
function enviarAppScriptPeletizado($payload){
    $payload['token'] = PELETIZADO_APPSCRIPT_TOKEN;
    $log = $payload['log'] ?? [];

    $resp = null;
    for($intento = 1; $intento <= 2; $intento++){
        $resp = enviarAppScriptPeletizadoUnaVez($payload);
        if(!empty($resp['ok'])){
            return $resp;
        }
        if(($resp['error'] ?? '') === 'yaExportado'){
            return $resp;
        }
        // Antes de reintentar: si la respuesta fue ambigua pero ya quedó
        // guardado, no tiene sentido enviar todo de nuevo.
        if($intento < 2 && !empty($log) && turnoYaEnRegistrosPeletizado($log['fecha'], $log['maquina'], $log['turno'])){
            return ['ok' => false, 'error' => 'yaExportado'];
        }
        if($intento < 2){ sleep(2); }
    }
    return $resp;
}

// ¿Ya quedaron guardados en REGISTROS los datos de este turno? Se fija en las
// filas del día, máquina y turno. Sirve tanto para confirmar un envío ambiguo
// como para saber, antes de abrir una planilla, si ese turno ya fue exportado
// alguna vez aunque la BD local no lo recuerde.
function turnoYaEnRegistrosPeletizado($fecha, $maquina, $turno){
    $ctx = stream_context_create(['http' => ['timeout' => 20]]);
    $fh = @fopen(PELETIZADO_REGISTROS_CSV_URL, 'r', false, $ctx);
    if(!$fh){
        return false;
    }
    fgetcsv($fh, 0, ',', '"', '\\'); // encabezado
    $encontrado = false;
    while(($fila = fgetcsv($fh, 0, ',', '"', '\\')) !== false){
        $f = $fila[1] ?? '';
        $fFormateada = DateTime::createFromFormat('d/m/Y', trim((string) $f));
        $fFormateada = $fFormateada ? $fFormateada->format('Y-m-d') : null;
        if($fFormateada === $fecha
            && trim((string) ($fila[2] ?? '')) === $maquina
            && trim((string) ($fila[3] ?? '')) === $turno
        ){
            $encontrado = true; // no se hace break: se queda con la última coincidencia
        }
    }
    fclose($fh);
    return $encontrado;
}
