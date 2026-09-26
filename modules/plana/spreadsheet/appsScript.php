<?php
// Cliente Apps Script de Plana

// CSV de la hoja REGISTROS
define('PLANA_REGISTROS_CSV_URL', 'https://docs.google.com/spreadsheets/d/1DO_G6MHfoMagMMEOUOipTiE6W1UC-65f7BamJZQwGSc/export?format=csv&gid=1759801026');

define('PLANA_APPSCRIPT_URL',   'https://script.google.com/macros/s/AKfycbxNoSXbaifRZNkWpFOD6mxx0Kw1R-0hG_Pyipf3xcZ1QC16Pl998VGNqiwGdmgvLMje4A/exec');
define('PLANA_APPSCRIPT_TOKEN', 'PLASTYPETCO_BODEGA1');

// Valida solo la forma
function appScriptConfiguradoPlana(){
    return strncmp(PLANA_APPSCRIPT_URL, 'https://', 8) === 0
        && strlen(PLANA_APPSCRIPT_URL) > 20
        && strlen(PLANA_APPSCRIPT_TOKEN) >= 6;
}

// POST al Web App
function enviarAppScriptPlana($payload){
    $payload['token'] = PLANA_APPSCRIPT_TOKEN;

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

    $resp = @file_get_contents(PLANA_APPSCRIPT_URL, false, $ctx);
    if($resp === false){
        return ['ok' => false, 'error' => 'No se pudo conectar con Google (Apps Script).'];
    }
    $data = json_decode($resp, true);
    if(!is_array($data)){
        return ['ok' => false, 'error' => 'Respuesta inesperada del Apps Script.'];
    }
    return $data;
}
