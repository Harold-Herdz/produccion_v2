<?php
// Cliente Apps Script de Mezclas

// CSV de la hoja REGISTROS
define('MEZCLA_REGISTROS_CSV_URL', 'https://docs.google.com/spreadsheets/d/10JvKK9SVouBV5qo7PUt23aQDbf-GWGU-YdcQ0LkquC8/export?format=csv&gid=0');

// URL del Web App desplegado
define('MEZCLA_APPSCRIPT_URL',   'https://script.google.com/macros/s/AKfycbzUg-eX0H64polPQxFOGvpZNWrX9mqc-Lxx4kLq6X2vAfB_pYfRrBTpefTkX3LtiSco/exec');
define('MEZCLA_APPSCRIPT_TOKEN', 'PLASTYPETCO_MEZCLAS');

// Valida solo la forma
function appScriptConfiguradoMezcla(){
    return strncmp(MEZCLA_APPSCRIPT_URL, 'https://', 8) === 0
        && strlen(MEZCLA_APPSCRIPT_URL) > 20
        && strlen(MEZCLA_APPSCRIPT_TOKEN) >= 6;
}

// Un intento de POST al Web App
function enviarAppScriptMezclaUnaVez($payload){
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

    $resp = @file_get_contents(MEZCLA_APPSCRIPT_URL, false, $ctx);
    if($resp === false){
        return ['ok' => false, 'error' => 'No se pudo conectar con Google (Apps Script).'];
    }
    $data = json_decode($resp, true);
    if(!is_array($data)){
        return ['ok' => false, 'error' => 'Respuesta inesperada del Apps Script.'];
    }
    return $data;
}

// POST al Web App con un reintento (Google a veces responde con algo que no
// es JSON aunque el dato sí haya quedado guardado)
function enviarAppScriptMezcla($payload){
    $payload['token'] = MEZCLA_APPSCRIPT_TOKEN;

    $resp = null;
    for($intento = 1; $intento <= 2; $intento++){
        $resp = enviarAppScriptMezclaUnaVez($payload);
        if(!empty($resp['ok'])){
            return $resp;
        }
        if($intento < 2){ sleep(2); }
    }
    return $resp;
}
