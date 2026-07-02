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

// Obtener todos los usuarios ordenados
$sql = "SELECT * FROM USUARIOS ORDER BY usuario";
$res = mysqli_query($conexion, $sql);
?>
<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8" name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuarios</title>
    <!-- Ícono y estilos -->
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/logo-plastypetco.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/usuarios.css">

</head>
<body>
    <!-- Contenedor de Usuarios -->
    <div class="container">
        <!-- Título -->
        <h2 class="titulo-vista">Administración de Usuarios</h2>

        <!-- Botón para crear usuario -->
        <div class="acciones">
            <a class="btn" onclick="abrirModal('modalCrear')">
                + Crear Usuario
            </a>
        </div>

        <br>
        
        <!-- Tabla de usuarios -->
        <div id="containerHistorial">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <!-- Filas de usuarios traídos de la base de datos -->
                <tbody>
                    <?php while($usuario = mysqli_fetch_assoc($res)){ ?>
                    <tr>
                        <td><?= $usuario['id_usuario'] ?></td>
                        <td><?= htmlspecialchars($usuario['usuario']) ?></td>
                        <td><?= ucfirst($usuario['rol']) ?></td>
                        <!-- Estado activo o inactivo -->
                        <td>
                        <?php if($usuario['estado']){ ?>
                            <span class="estado activo">Activo</span>
                        <?php } else { ?>
                            <span class="estado inactivo">Inactivo</span>
                        <?php } ?>
                        </td>
                         
                        <!-- Botón editar -->
                        <td>
                            <a class="btn"
                                onclick="abrirEditar(
                                    <?= $usuario['id_usuario'] ?>,
                                    '<?= htmlspecialchars($usuario['usuario']) ?>',
                                    '<?= $usuario['rol'] ?>',
                                    <?= $usuario['estado'] ?>
                                )"
                            >
                                Editar
                            </a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    </div>

</body>


<!-- Modal de Crear -->
<div class="overlay" id="modalCrear">
    <div class="modal">
        <!-- Título -->
        <h2 class="titulo-modal">Crear Usuario</h2>

        <!-- Formulario de creación -->
        <form action="<?= BASE_URL ?>/auth/controllers/guardarUsuario.php" method="POST">
            <!-- Campo usuario -->
            <label>Usuario</label>
            <input type="text" name="usuario" required>

            <!-- Campo contraseña -->
            <label>Contraseña</label>
            <input type="text" name="contrasena" required>

            <!-- Selector de rol -->
            <label>Rol</label>
            <select name="rol" required>
                <option value="operador">Operador</option>
                <option value="admin">Administrador</option>
            </select>

            <!-- Botones de acción -->
            <div class="accionesModal">
                <button class="btn" type="submit" onclick="cerrarModal('modalCrear')">
                    Guardar
                </button>

                <button class="btn" onclick="cerrarModal('modalCrear')">
                    Cancelar
                </button>
            </div>
        </form>

    </div>
</div>

<!-- Modal de Editar -->
<div class="overlay" id="modalEditar">
    <div class="modal">
        <!-- Título -->
        <h2 class="titulo-modal">Editar Usuario</h2>

        <!-- Formulario de edición -->
        <form action="<?= BASE_URL ?>/auth/controllers/actualizarUsuario.php" method="POST">
            <!-- ID del usuario a actualizar -->    
            <input type="hidden" id="edit_id" name="id_usuario">

            <!-- Campo usuario -->
            <label>Usuario</label>
            <input type="text" id="edit_usuario" name="usuario"
                required>

            <!-- Campo contraseña (opcional) -->
            <label>Nueva Contraseña</label>
            <input type="text" id="edit_contrasena" name="contrasena" placeholder="Opcional">

            <!-- Selector de rol -->
            <label>Nuevo Rol</label>
            <select id="edit_rol" name="rol">
                <option value="operador">Operador</option>
                <option value="admin">Administrador</option>
            </select>

            <!-- Selector de estado -->
            <label>Estado</label>
            <select id="edit_estado" name="estado">
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>

            <!-- Botones de acción -->
            <div class="accionesModal">
                <button class="btn" type="submit" onclick="cerrarModal('modalEditar')">
                    Guardar
                </button>

                <button class="btn" onclick="cerrarModal('modalEditar')">
                    Cancelar
                </button>
            </div>
        </form>

    </div>
</div>

<!-- Botón para volver -->
<a id="btn-volver" href="<?= BASE_URL ?>/index.php">← Volver</a>

<script src="<?= BASE_URL ?>/auth/shared/auth.js"></script>

</html>