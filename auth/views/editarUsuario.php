<?php
/** @var mysqli $conexion */

// Restringir acceso solo a administradores
$soloAdmin = true;
// Importar proteger.php
require_once dirname(__DIR__) . '/proteger.php';
// Importar conexion.php
require_once dirname(__DIR__, 2) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 2) . '/includes/config.php';
// Importar header.php
require_once dirname(__DIR__, 2) . '/templates/header.php';

// Obtener ID del usuario a editar
$id = (int)($_GET['id'] ?? 0);

// Buscar usuario en la BD
$sql = "SELECT * FROM USUARIOS WHERE id_usuario = $id LIMIT 1";
$res = mysqli_query($conexion, $sql);

// Verificar que existe
if(mysqli_num_rows($res) == 0){
    die("Usuario no encontrado");
}

$usuario = mysqli_fetch_assoc($res);
?>

<!-- Conteneder de Editar -->
<div class="container">
    <!-- Título -->
    <h2 class="titulo-vista">Editar Usuario</h2>

    <!-- Formulario de edición -->
    <form action="<?= BASE_URL ?>/auth/controllers/actualizarUsuario.php" method="POST">
        <!-- ID del usuario a actualizar -->    
        <input type="hidden" name="id_usuario"
            value="<?= $usuario['id_usuario'] ?>">

        <!-- Campo usuario -->
        <label>Usuario</label>
        <input type="text" name="usuario"
            value="<?= htmlspecialchars($usuario['usuario']) ?>"
            required>

        <!-- Campo contraseña (opcional) -->
        <label>Nueva Contraseña</label>
        <input type="text" name="contrasena" placeholder="Opcional">

        <!-- Selector de rol -->
        <label>Nuevo Rol</label>
        <select name="rol">
            <option value="operador"
                <?= $usuario['rol'] == 'operador' ? 'selected' : '' ?>>
                Operador
            </option>

            <option value="admin"
                <?= $usuario['rol'] == 'admin' ? 'selected' : '' ?>>
                Administrador
            </option>
        </select>

        <!-- Selector de estado -->
        <label>Estado</label>
        <select name="estado">
            <option value="1"
                <?= $usuario['estado'] == 1 ? 'selected' : '' ?>>
                Activo
            </option>

            <option value="0"
                <?= $usuario['estado'] == 0 ? 'selected' : '' ?>>
                Inactivo
            </option>
        </select>

        <!-- Botones de acción -->
        <div class="acciones">
            <button class="btn" type="submit">
                Guardar Cambios
            </button>

            <a class="btn" href="<?= BASE_URL ?>/auth/views/usuarios.php">
                Cancelar
            </a>
        </div>
    </form>

</div>

<?php 
// Importar footer.php
include dirname(__DIR__, 2) . '/templates/footer.php';
?>