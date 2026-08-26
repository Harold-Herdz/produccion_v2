<?php
// Iniciar la sesión si no hay una activa
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// Importar config.php
require_once dirname(__DIR__, 2) . '/includes/config.php';

// Redirigir al Index si yá inició sesión
if(isset($_SESSION['usuario'])){
    header("Location: /index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar Sesión</title>
    <!-- Ícono y estilos -->
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login.css">

</head>

<body>
    <!-- Contenedor del login -->
    <div class="login-box">

        <!-- Logo -->
        <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Logo Plastypetco">
        
        <!-- Mensaje de error -->
        <h1>Iniciar Sesión</h1>

        <!-- Formulario de autenticación -->
        <form action="<?= BASE_URL ?>/auth/controllers/validate.php" method="POST">

            <!-- Campo usuario -->
            <div class="grupo">
                <label>Usuario</label>
                <input type="text" name="usuario" autocomplete="new-password" placeholder="Usuario" required>
            </div>

            <!-- Campo contraseña -->
            <div class="grupo">
                <label>Contraseña</label>
                <div class="pass-wrap">
                    <input type="password" id="contrasena" name="contrasena" autocomplete="new-password" placeholder="•••••••••••" required>
                    <button type="button" class="toggle-pass" onclick="togglePassword()" aria-label="Mostrar contraseña">
                        <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>


            <!-- Botón ingresar -->
            <button class="btn" type="submit">
                Ingresar
            </button>
            <?php if(isset($_GET['error'])){ ?>
            <div class="error">
                Usuario o contraseña incorrectos
            </div>
            <?php } ?>

        </form>

    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('contrasena');
            const icon = document.getElementById('eye-icon');
            const showing = input.type === 'text';

            input.type = showing ? 'password' : 'text';

            icon.innerHTML = showing
                ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>'
                : '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.6 21.6 0 0 1 5.06-6.06M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.6 21.6 0 0 1-3.22 4.6M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
        };
    </script>
</body>
</html>