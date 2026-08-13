<?php
// Iniciar la sesión si no hay una activa
if(session_status() === PHP_SESSION_NONE){
    session_set_cookie_params(28800); // Duración de la sesión: 8 horas
    session_start();
}

// Importar config.php
require_once dirname(__DIR__) . '/includes/config.php';

// Redirigir al Login si no hay sesión activa
if(!isset($_SESSION['usuario'])){
    header("Location: " . BASE_URL . "/auth/views/login.php");
    exit;
}

// Restringir acceso solo a administradores
if(isset($soloAdmin) && $soloAdmin === true){
    if($_SESSION['rol'] != 'admin'){
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }
}