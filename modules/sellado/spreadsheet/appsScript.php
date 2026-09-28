<?php
// Cliente Apps Script Web App

define('SELLADO_APPSCRIPT_URL',   'https://script.google.com/macros/s/AKfycbxnoTaeH-OnppHLsFLcZFKZM-XgYHzGcMYFMwd4atTZHE7NmyT1HeFJXBVVWYYh6fyy/exec');
define('SELLADO_APPSCRIPT_TOKEN', 'PLASTYPETCO_BODEGA1');
// LOGS del Sheet (para confirmar un turno cuando la respuesta del Apps Script no es clara)
define('SELLADO_LOGS_CSV_URL', 'https://docs.google.com/spreadsheets/d/1B1A-pSUBLG9w56ibWcERxhAKEsaPJN74SjRjeNv2UCg/gviz/tq?tqx=out:csv&sheet=LOGS');

// Validar solo la forma
function appScriptConfigurado(){
    return strncmp(SELLADO_APPSCRIPT_URL, 'https://', 8) === 0
        && strlen(SELLADO_APPSCRIPT_URL) > 20
        && strlen(SELLADO_APPSCRIPT_TOKEN) >= 6;
}

// Un intento de POST al Web App
function enviarAppScriptUnaVez($payload){
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

    $resp = @file_get_contents(SELLADO_APPSCRIPT_URL, false, $ctx);
    if($resp === false){
        return ['ok' => false, 'error' => 'No se pudo conectar con Google (Apps Script).'];
    }
    $data = json_decode($resp, true);
    if(!is_array($data)){
        return ['ok' => false, 'error' => 'Respuesta inesperada del Apps Script.'];
    }
    return $data;
}

// POST al Web App con reintentos: Google a veces responde con una página que no
// es JSON aunque el turno sí haya quedado guardado (falla solo la respuesta, no
// el guardado). Reintentar aquí evita mostrar un error cuando en realidad funcionó.
function enviarAppScript($payload){
    $payload['token'] = SELLADO_APPSCRIPT_TOKEN;
    $codigo = $payload['codigo'] ?? null;

    $resp = null;
    for($intento = 1; $intento <= 2; $intento++){
        $resp = enviarAppScriptUnaVez($payload);
        if(!empty($resp['ok'])){
            return $resp;
        }
        // Error de negocio real (no de red/formato): no tiene sentido reintentar
        if(($resp['error'] ?? '') === 'yaExportado'){
            return $resp;
        }
        // Antes de reintentar: si la respuesta fue ambigua pero el turno ya
        // quedó guardado, no tiene sentido enviar todo de nuevo.
        if($intento < 2 && $codigo && turnoYaEnLogs($codigo)){
            return ['ok' => false, 'error' => 'yaExportado'];
        }
        if($intento < 2){ sleep(2); }
    }
    return $resp;
}

// ¿El turno ya quedó guardado en LOGS? (confirma un envío cuya respuesta no fue clara)
function turnoYaEnLogs($codigo){
    $ctx = stream_context_create(['http' => ['timeout' => 20]]);
    $fh = @fopen(SELLADO_LOGS_CSV_URL, 'r', false, $ctx);
    if(!$fh){
        return false;
    }
    fgetcsv($fh); // encabezado
    $encontrado = false;
    while(($fila = fgetcsv($fh)) !== false){
        if(trim((string) ($fila[0] ?? '')) === $codigo){
            $encontrado = true;
            break;
        }
    }
    fclose($fh);
    return $encontrado;
}
