<?php
// Config y cliente del Apps Script Web App (recibe REGISTROS + LOGS + PDF al finalizar)

define('SELLADO_APPSCRIPT_URL',   'https://script.google.com/macros/s/AKfycbxnoTaeH-OnppHLsFLcZFKZM-XgYHzGcMYFMwd4atTZHE7NmyT1HeFJXBVVWYYh6fyy/exec');
define('SELLADO_APPSCRIPT_TOKEN', 'PLASTYPETCO_BODEGA1');

// Valida solo la forma (no compara contra placeholders, para no romperse con un reemplazo)
function appScriptConfigurado(){
    return strncmp(SELLADO_APPSCRIPT_URL, 'https://', 8) === 0
        && strlen(SELLADO_APPSCRIPT_URL) > 20
        && strlen(SELLADO_APPSCRIPT_TOKEN) >= 6;
}

// POST al Web App; devuelve la respuesta decodificada
function enviarAppScript($payload){
    $payload['token'] = SELLADO_APPSCRIPT_TOKEN;

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
