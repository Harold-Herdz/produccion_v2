<?php
// Página de error amigable (nunca detalles técnicos)
if (!headers_sent()) {
    http_response_code(500);
}
$volver = $_SERVER['HTTP_REFERER'] ?? (defined('BASE_URL') ? BASE_URL . '/index.php' : '/');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ocurrió un error</title>
<style>
    body{font-family:Arial,Helvetica,sans-serif;background:#f4f6f5;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
    .caja{background:#fff;padding:32px 40px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.08);max-width:420px;text-align:center;}
    h1{color:#164a7d;font-size:20px;margin:0 0 12px;}
    p{color:#333;font-size:15px;line-height:1.5;margin:0 0 10px;}
    a{display:inline-block;margin-top:14px;background:#164a7d;color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;}
</style>
</head>
<body>
    <div class="caja">
        <h1>Ocurrió un error</h1>
        <p>No se pudo completar la acción. Puede ser una conexión lenta o algo pasajero.</p>
        <p>Vuelve a intentarlo en un momento; si ya se había guardado, no se duplicará.</p>
        <a href="<?= htmlspecialchars($volver, ENT_QUOTES) ?>">Volver</a>
    </div>
</body>
</html>
