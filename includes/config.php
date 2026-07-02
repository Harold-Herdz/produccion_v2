<?php 
// Detectar el entorno
if ($_SERVER['HTTP_HOST'] === 'localhost') {
    define('BASE_URL', '/produccion');
} else if ($_SERVER['HTTP_HOST'] === 'controlproduccion-plastypetco.site-je') {
    define('BASE_URL', '');
} else {
    define('BASE_URL', '');
}