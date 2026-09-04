<?php
/** @var mysqli        $conexion  */
/** @var array         $cfg       Configuración del catálogo activo */
/** @var string        $clave     Clave del catálogo activo */
/** @var string        $busqueda  Texto de búsqueda actual */
/** @var mysqli_result $registros Registros a listar */

// Restringir acceso solo a administradores
$soloAdmin = true;
// Importar authMiddleware.php
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar catalogsController.php  (prepara $cfg, $clave, $busqueda, $registros)
include dirname(__DIR__) . '/controllers/catalogsController.php';

// Lista de catálogos disponibles para el selector superior
$catalogos = catalogosDisponibles();

// Número de columnas de la tabla (turnos muestra 2 columnas extra)
$totalColumnas = ($clave === 'turnos') ? 5 : 4;

// Importar header.php
include dirname(__DIR__, 3) . '/templates/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/catalogs.css">

    <!-- Contenedor de Catálogos -->
    <div class="container">

        <!-- Título -->
        <h2 class="titulo-vista">Administración de Catálogos</h2>

        <!-- Barra superior: selector de catálogo + búsqueda -->
        <form class="barra-superior" method="GET">
            <!-- Selector del catálogo a administrar -->
            <div class="grupo-campo">
                <label for="cat">Catálogo</label>
                <select id="cat" name="cat" onchange="this.form.submit()">
                    <?php foreach ($catalogos as $key => $datos) { ?>
                        <option value="<?= $key ?>" <?= ($key === $clave) ? 'selected' : '' ?>>
                            <?= $datos['etiqueta'] ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <!-- Búsqueda por nombre -->
            <div class="grupo-campo">
                <label for="buscar">Buscar</label>
                <input type="text" id="buscar" name="buscar" autocomplete="off"
                    placeholder="Buscar por nombre..."
                    value="<?= htmlspecialchars($busqueda) ?>">
            </div>

            <!-- Botones de filtro -->
            <div class="grupo-campo grupo-botones">
                <button type="submit" class="btn">Filtrar</button>
                <a class="btn btn-secundario" href="?cat=<?= $clave ?>">Limpiar</a>
            </div>
        </form>

        <!-- Acción: crear nuevo registro -->
        <div class="acciones">
            <a class="btn" onclick="abrirModal('modalCrear')">
                + Crear <?= $cfg['etiqueta'] ?>
            </a>
        </div>

        <br>

        <!-- Tabla del catálogo -->
        <div id="containerHistorial">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>ID</th>
                        <?php if ($clave === 'turnos') { ?>
                            <th>Bloque horario</th>
                            <th>Jornada</th>
                        <?php } ?>
                        <th>Nombre</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <!-- Filas del catálogo traídas de la base de datos -->
                <tbody>
                    <?php if ($registros && mysqli_num_rows($registros) > 0) { ?>
                        <?php while ($fila = mysqli_fetch_assoc($registros)) { ?>
                        <tr>
                            <!-- ID -->
                            <td><?= $fila[$cfg['id']] ?></td>

                            <!-- Columnas propias de Turnos -->
                            <?php if ($clave === 'turnos') { ?>
                                <td><?= htmlspecialchars($fila['bloque_horario']) ?></td>
                                <td><?= htmlspecialchars($fila['jornada']) ?></td>
                            <?php } ?>

                            <!-- Nombre visible -->
                            <td><?= htmlspecialchars($fila[$cfg['nombre']]) ?></td>

                            <!-- Estado activo o inhabilitado -->
                            <td>
                            <?php if ($fila['estado']) { ?>
                                <span class="estado activo">Activo</span>
                            <?php } else { ?>
                                <span class="estado inactivo">Inhabilitado</span>
                            <?php } ?>
                            </td>

                            <!-- Botón de acción: alternar estado -->
                            <td>
                                <form method="POST"
                                    onsubmit="return confirmarEstado(<?= $fila['estado'] ? 1 : 0 ?>);">
                                    <input type="hidden" name="cat" value="<?= $clave ?>">
                                    <input type="hidden" name="accion" value="estado">
                                    <input type="hidden" name="id" value="<?= $fila[$cfg['id']] ?>">
                                    <?php if ($fila['estado']) { ?>
                                        <button type="submit" class="btn btn-inhabilitar">Inhabilitar</button>
                                    <?php } else { ?>
                                        <button type="submit" class="btn btn-activar">Activar</button>
                                    <?php } ?>
                                </form>
                            </td>
                        </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <!-- Sin resultados -->
                        <tr>
                            <td colspan="<?= $totalColumnas ?>">Sin registros para mostrar.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Modal de Crear -->
    <div class="overlay" id="modalCrear">
        <div class="modal">
            <!-- Título -->
            <h2 class="titulo-modal">Crear <?= $cfg['etiqueta'] ?></h2>

            <!-- Formulario de creación -->
            <form action="<?= BASE_URL ?>/modules/catalogs/controllers/catalogsController.php" method="POST">
                <!-- Catálogo y acción -->
                <input type="hidden" name="cat" value="<?= $clave ?>">
                <input type="hidden" name="accion" value="crear">

                <!-- Campos dinámicos según el catálogo -->
                <?php foreach ($cfg['campos'] as $columna => $meta) { ?>
                    <label><?= $meta['etiqueta'] ?></label>

                    <?php if ($meta['tipo'] === 'select') { ?>
                        <select name="<?= $columna ?>" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($meta['opciones'] as $opcion) { ?>
                                <option value="<?= $opcion ?>"><?= $opcion ?></option>
                            <?php } ?>
                        </select>
                    <?php } else { ?>
                        <input type="text" name="<?= $columna ?>" required>
                    <?php } ?>
                <?php } ?>

                <!-- Botones de acción -->
                <div class="accionesModal">
                    <button class="btn" type="submit">Guardar</button>
                    <button class="btn btn-secundario" type="button"
                        onclick="cerrarModal('modalCrear')">Cancelar</button>
                </div>
            </form>

        </div>
    </div>

    <!-- Botón para volver -->
    <a id="btn-volver" href="<?= BASE_URL ?>/index.php">← Volver</a>

    <script src="<?= BASE_URL ?>/modules/catalogs/scripts/catalogs.js"></script>

<?php include dirname(__DIR__, 3) . '/templates/footer.php'; ?>
