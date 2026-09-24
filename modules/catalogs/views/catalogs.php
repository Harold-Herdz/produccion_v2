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

// Número de columnas de la tabla (Operarios suma la columna "Supervisor")
$totalColumnas = ($clave === 'operarios') ? 5 : 4;

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
            <!-- Selector del catálogo o relación a administrar -->
            <div class="grupo-campo">
                <label for="cat">Catálogo</label>
                <select id="cat" name="cat" onchange="this.form.submit()">
                    <?php foreach ($catalogos as $key => $datos) { ?>
                        <option value="<?= $key ?>" <?= ($key === $clave) ? 'selected' : '' ?>>
                            <?= $datos['etiqueta'] ?>
                        </option>
                    <?php } ?>
                    <option value="maquina_areas" <?= ($clave === 'maquina_areas') ? 'selected' : '' ?>>
                        Relación: Máquinas × Áreas
                    </option>
                    <option value="maquina_referencias" <?= ($clave === 'maquina_referencias') ? 'selected' : '' ?>>
                        Relación: Máquinas × Referencias
                    </option>
                </select>
            </div>

            <?php if (!$esMatriz) { ?>
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
            <?php } ?>
        </form>

        <?php if (!$esMatriz) { ?>
        <!-- Acción: crear nuevo registro -->
        <div class="acciones" id="accionesCatalogos">
            <a class="btn" onclick="abrirModal('modalCrear')">
                + Crear <?= $cfg['etiqueta'] ?>
            </a>
            <a class="btn btn-secundario" onclick="mostrarPanelCatalogos('exportar')">
                Exportar
            </a>
            <a class="btn btn-secundario" onclick="mostrarPanelCatalogos('importar')">
                Importar
            </a>
        </div>
        <?php } ?>

        <!-- Panel de exportar (reemplaza la tabla mientras está abierto) -->
        <div id="panelExportar" class="panel-catalogos" style="display:none;">
            <h3 class="subtitulo-panel">Exportar catálogos</h3>
            <p class="texto-panel">Guardar el contenido actual de cada catálogo.</p>
            <form id="formExportarCatalogos" data-url="<?= BASE_URL ?>/modules/catalogs/controllers/catalogsController.php">
                <input type="hidden" name="cat" value="<?= $clave ?>">
                <input type="hidden" name="accion" value="exportar">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Catálogo</th>
                            <th>Seleccionar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Usuarios</td>
                            <td><input type="checkbox" name="catalogos[]" value="usuarios" checked></td>
                        </tr>
                        <?php foreach ($catalogos as $key => $datos) { ?>
                        <tr>
                            <td><?= htmlspecialchars($datos['etiqueta']) ?></td>
                            <td><input type="checkbox" name="catalogos[]" value="<?= $key ?>" checked></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <div class="acciones">
                    <button class="btn" type="submit">Exportar</button>
                    <a class="btn btn-secundario" onclick="ocultarPanelesCatalogos()">Cancelar</a>
                </div>
            </form>
        </div>

        <!-- Panel de importar (reemplaza la tabla mientras está abierto) -->
        <div id="panelImportar" class="panel-catalogos" style="display:none;">
            <h3 class="subtitulo-panel">Importar catálogos</h3>
            <p class="texto-panel">Agrega a cada catálogo su contenido guardado.</p>
            <form id="formImportarCatalogos" data-url="<?= BASE_URL ?>/modules/catalogs/controllers/catalogsController.php">
                <input type="hidden" name="cat" value="<?= $clave ?>">
                <input type="hidden" name="accion" value="importar">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Catálogo</th>
                            <th>Seleccionar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Usuarios</td>
                            <td><input type="checkbox" name="catalogos[]" value="usuarios" checked></td>
                        </tr>
                        <?php foreach ($catalogos as $key => $datos) { ?>
                        <tr>
                            <td><?= htmlspecialchars($datos['etiqueta']) ?></td>
                            <td><input type="checkbox" name="catalogos[]" value="<?= $key ?>" checked></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <div class="acciones">
                    <button class="btn" type="submit">Importar</button>
                    <a class="btn btn-secundario" onclick="ocultarPanelesCatalogos()">Cancelar</a>
                </div>
            </form>
        </div>

        <?php if ($clave === 'maquina_areas') {
            $matriz = obtenerMatrizMaquinaAreas($conexion);
        ?>
        <!-- Matriz: en qué área (módulo) se usa cada máquina -->
        <div class="matriz-scroll" data-url="<?= BASE_URL ?>/modules/catalogs/controllers/catalogsController.php">
            <table class="tabla tabla-matriz">
                <thead>
                    <tr>
                        <th class="col-matriz-nombre">Máquina</th>
                        <?php foreach ($matriz['areas'] as $area) { ?>
                            <th><?= htmlspecialchars(ucfirst($area['nombre_area'])) ?></th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matriz['maquinas'] as $maq) { ?>
                    <tr>
                        <td class="col-matriz-nombre"><?= htmlspecialchars($maq['nombre_maquina']) ?></td>
                        <?php foreach ($matriz['areas'] as $area) { ?>
                        <td class="col-matriz-check">
                            <input type="checkbox" class="chk-matriz"
                                data-accion="toggle_maquina_area"
                                data-maquina="<?= $maq['id_maquina'] ?>"
                                data-area="<?= $area['id_area'] ?>"
                                <?= isset($matriz['relaciones'][$maq['id_maquina']][$area['id_area']]) ? 'checked' : '' ?>>
                        </td>
                        <?php } ?>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php } elseif ($clave === 'maquina_referencias') {
            $matriz = obtenerMatrizMaquinaReferencias($conexion);
        ?>
        <!-- Matriz: qué referencias produce cada máquina ("Especiales" = usa Referencias Especiales completas) -->
        <div class="matriz-scroll" data-url="<?= BASE_URL ?>/modules/catalogs/controllers/catalogsController.php">
            <table class="tabla tabla-matriz">
                <thead>
                    <tr>
                        <th class="col-matriz-nombre">Máquina</th>
                        <th class="col-matriz-esp">Especiales</th>
                        <?php foreach ($matriz['referencias'] as $ref) { ?>
                            <th><?= htmlspecialchars($ref['nombre_referencia']) ?></th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matriz['maquinas'] as $maq) { $esp = (bool) $maq['usa_referencias_esp']; ?>
                    <tr class="<?= $esp ? 'fila-usa-esp' : '' ?>">
                        <td class="col-matriz-nombre"><?= htmlspecialchars($maq['nombre_maquina']) ?></td>
                        <td class="col-matriz-check col-matriz-esp">
                            <input type="checkbox" class="chk-matriz"
                                data-accion="toggle_maquina_esp"
                                data-maquina="<?= $maq['id_maquina'] ?>"
                                <?= $esp ? 'checked' : '' ?>>
                        </td>
                        <?php foreach ($matriz['referencias'] as $ref) { ?>
                        <td class="col-matriz-check">
                            <input type="checkbox" class="chk-matriz"
                                data-accion="toggle_maquina_referencia"
                                data-maquina="<?= $maq['id_maquina'] ?>"
                                data-referencia="<?= $ref['id_referencia'] ?>"
                                <?= isset($matriz['relaciones'][$maq['id_maquina']][$ref['id_referencia']]) ? 'checked' : '' ?>
                                <?= $esp ? 'disabled' : '' ?>>
                        </td>
                        <?php } ?>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php } else { ?>
        <!-- Tabla del catálogo -->
        <div id="containerHistorial">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <?php if ($clave === 'operarios') { ?>
                            <th>Supervisor</th>
                        <?php } ?>
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

                            <!-- Nombre visible -->
                            <td><?= htmlspecialchars($fila[$cfg['nombre']]) ?></td>

                            <!-- Supervisor: solo en Operarios -->
                            <?php if ($clave === 'operarios') { ?>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="cat" value="<?= $clave ?>">
                                    <input type="hidden" name="accion" value="toggle_supervisor">
                                    <input type="hidden" name="id" value="<?= $fila[$cfg['id']] ?>">
                                    <button type="submit"
                                        class="switch-supervisor <?= $fila['es_supervisor'] ? 'on' : 'off' ?>"
                                        title="<?= $fila['es_supervisor'] ? 'Quitar supervisor' : 'Marcar supervisor' ?>">
                                        <span class="switch-no">No</span>
                                        <span class="switch-si">Sí</span>
                                        <span class="switch-thumb"></span>
                                    </button>
                                </form>
                            </td>
                            <?php } ?>

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
        <?php } ?>

    </div>

    <!-- Overlay de carga (exportar/importar en curso) -->
    <div class="overlay" id="overlayCargaCatalogos">
        <div class="tarjeta-carga">
            <span class="spinner-carga"></span>
            <p id="textoCargaCatalogos">Exportando…</p>
        </div>
    </div>

    <!-- Modal de resultado (exportar/importar) -->
    <div class="overlay" id="modalResultadoCatalogos">
        <div class="modal modal-resultado-catalogos">
            <button type="button" class="cerrar-resultado" onclick="cerrarModal('modalResultadoCatalogos')">&times;</button>
            <h2 class="titulo-modal" id="tituloResultadoCatalogos">Exportación completada</h2>
            <ul class="lista-resultado-catalogos" id="listaResultadoCatalogos"></ul>
        </div>
    </div>

    <?php if (!$esMatriz) { ?>
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
                    <?php } elseif ($meta['tipo'] === 'checkbox') { ?>
                        <input type="checkbox" name="<?= $columna ?>" value="1">
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
    <?php } ?>

    <script src="<?= BASE_URL ?>/modules/catalogs/scripts/catalogs.js"></script>

<?php include dirname(__DIR__, 3) . '/templates/footer.php'; ?>
