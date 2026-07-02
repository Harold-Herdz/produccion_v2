<?php
// Iniciar la sesión si no hay una activa
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// Importar config.php
require_once dirname(__DIR__, 2) . '/includes/config.php';

// Borrar la sesión
session_destroy();

// Redirigir al Login
header("Location: " . BASE_URL . "/auth/views/login.php");
exit;