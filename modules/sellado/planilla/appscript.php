<?php
/* =================================================
   CONFIGURACIÓN DEL APPS SCRIPT (Web App) DE SELLADO
   -------------------------------------------------
   Al finalizar el turno, finalizarPlanilla.php hace un
   POST a esta URL con las filas de REGISTROS, la fila de
   LOGS y el PDF en base64. El Apps Script escribe en el
   spreadsheet y guarda el PDF en Drive.

   PASOS PARA CONFIGURAR:
   1. Abre el spreadsheet de REGISTROS/LOGS → Extensiones → Apps Script.
   2. Pega el contenido de appscript.gs (en esta misma carpeta).
   3. En appscript.gs cambia TOKEN por un valor secreto.
   4. Implementar → Nueva implementación → Aplicación web
        - Ejecutar como: Yo
        - Quién tiene acceso: Cualquier usuario
   5. Copia la URL (/exec) y pégala abajo, junto con el mismo token.
================================================= */

// >>> REEMPLAZAR estos dos valores tras desplegar el Apps Script <<<
define('SELLADO_APPSCRIPT_URL',   'https://TU-URL-DEL-WEB-APP/exec');
define('SELLADO_APPSCRIPT_TOKEN', 'TU-TOKEN-SECRETO');

// ¿Está configurado el Web App? (URL http válida y sin el placeholder)
function appScriptConfigurado(){
    $url = SELLADO_APPSCRIPT_URL;
    return strncmp($url, 'http', 4) === 0
        && strpos($url, 'TU-URL-DEL-WEB-APP') === false
        && SELLADO_APPSCRIPT_TOKEN !== 'TU-TOKEN-SECRETO'
        && SELLADO_APPSCRIPT_TOKEN !== '';
}

// Enviar el POST al Web App y devolver la respuesta decodificada
function enviarAppScript($payload){
    $payload['token'] = SELLADO_APPSCRIPT_TOKEN;

    $ctx = stream_context_create([
        'http' => [
            'method'          => 'POST',
            'header'          => "Content-Type: application/json\r\n",
            'content'         => json_encode($payload),
            'timeout'         => 90,
            'follow_location' => 1,   // Apps Script redirige a script.googleusercontent.com
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
