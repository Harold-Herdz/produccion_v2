<?php
/** @var string $volver_url */

// Restringir acceso solo a administradores
$soloAdmin = true;
// Importar proteger.php
require_once dirname(__DIR__, 2) . '/auth/proteger.php';
// Importar config.php
require_once dirname(__DIR__, 2) . '/includes/config.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Importando <?php echo $titulo; ?></title>
    <!-- Fuente y estilos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/importar.css">

</head>
<body>
    <!-- Contenedor del importar -->
    <div class="wrapper">

        <div class="top">

            <!-- Título y subtítulo de la importación -->
            <div>
                <h1><?php echo $titulo; ?></h1>
                <p>Google Sheets &rarr; MySQL &middot; <?php echo $subtitulo; ?></p>
            </div>

        </div>

        <div class="card">

            <!-- Estado actual del proceso -->
            <div class="status-row">
                <span class="dot idle" id="dot"></span>
                <span id="status-lbl">Iniciando</span>
            </div>

            <!-- Mensaje de progreso -->
            <div id="msg">Preparando importación…</div>

            <!-- Barra de progreso -->
            <div class="track">
                <div id="fill"></div>
            </div>

            <!-- Porcentaje y contador de registros -->
            <div class="num-row">
                <div class="pct-wrap">
                    <span id="pct">0</span><span class="sign">%</span>
                </div>
                <span id="counter">— / — registros</span>
            </div>

            <!-- Estadísticas finales -->
            <div id="stats">
                <div class="st ok">
                    <div class="v" id="s-ok">0</div>
                    <div class="l">Insertados</div>
                </div>

                <div class="st upd">
                    <div class="v" id="s-upd">0</div>
                    <div class="l">Actualizados</div>
                </div>

                <div class="st dup">
                    <div class="v" id="s-dup">0</div>
                    <div class="l">Duplicados</div>
                </div>

                <div class="st tot">
                    <div class="v" id="s-tot">0</div>
                    <div class="l">Total</div>
                </div>
            </div>

            <!-- Botón para mostrar/ocultar log -->
            <button id="toggle-btn" onclick="toggleLog()">
                Ver detalle de registros
                <svg width="11" height="11" viewBox="0 0 12 12" fill="none">
                    <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.6"
                        stroke-linecap="round" stroke-linejoin="round"/>
                </svg>

            </button>

            <!-- Log de registros procesados -->
            <div id="log"></div>
            
        </div>

    </div>
<!-- Botón para volver -->
<a id="btn-volver" href="#" onclick="volverAtras(); return false;">← Volver</a>

<!-- URL de retorno y scripts -->
<script>window.VOLVER_URL = '<?php echo $volver_url; ?>';</script>
<script src="<?= BASE_URL ?>/importar/scripts/importar.js"></script>