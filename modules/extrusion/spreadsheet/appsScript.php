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

// POST al Web App
function enviarAppScriptExtrusion($payload){
    $payload['token'] = EXTRUSION_APPSCRIPT_TOKEN;

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
