<?php
// Cliente Apps Script de Rollos

// CSV de la hoja REGISTROS
define('ROLLO_REGISTROS_CSV_URL', 'https://docs.google.com/spreadsheets/d/1LtibtaYF6GEsXE5Mxgq6uq8BR_ZEQ1idlqFUof5mgRo/export?format=csv&gid=46026898');

define('ROLLO_APPSCRIPT_URL',   'https://script.google.com/macros/s/AKfycbzpR6Rc_sZllpDZQwwnFWq2So-1h0nokfjsZddODkyvYpppTnI7XJnyGQmHRBnBVXHm/exec');
define('ROLLO_APPSCRIPT_TOKEN', 'PLASTYPETCO_BODEGA1');

// Valida solo la forma
function appScriptConfiguradoRollo(){
    return strncmp(ROLLO_APPSCRIPT_URL, 'https://', 8) === 0
        && strlen(ROLLO_APPSCRIPT_URL) > 20
        && strlen(ROLLO_APPSCRIPT_TOKEN) >= 6;
}

// POST al Web App
function enviarAppScriptRollo($payload){
    $payload['token'] = ROLLO_APPSCRIPT_TOKEN;

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

    $resp = @file_get_contents(ROLLO_APPSCRIPT_URL, false, $ctx);
    if($resp === false){
        return ['ok' => false, 'error' => 'No se pudo conectar con Google (Apps Script).'];
    }
    $data = json_decode($resp, true);
    if(!is_array($data)){
        return ['ok' => false, 'error' => 'Respuesta inesperada del Apps Script.'];
    }
    return $data;
}
