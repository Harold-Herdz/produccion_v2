<?php
/** @var mysqli $conexion */
/** @var string $rel Relación activa: areas | referencias */
/** @var array  $areas */
/** @var int    $idArea */

// Restringir acceso solo a administradores
$soloAdmin = true;
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Prepara $rel, $areas, $idArea y atiende POST
include dirname(__DIR__) . '/controllers/relationsController.php';

$urlControlador = BASE_URL . '/modules/catalogs/controllers/relationsController.php';

include dirname(__DIR__, 3) . '/templates/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/catalogs.css">

    <div class="container">

        <h2 class="titulo-vista">Administrar Relaciones</h2>

        <!-- Selector de relación -->
        <form class="barra-superior" method="GET">
            <div class="grupo-campo">
                <label for="rel">Relación</label>
                <select id="rel" name="rel" onchange="this.form.submit()">
                    <option value="areas" <?= $rel === 'areas' ? 'selected' : '' ?>>Máquinas × Áreas</option>
                    <option value="referencias" <?= $rel === 'referencias' ? 'selected' : '' ?>>Referencias × Máquinas</option>
                    <option value="operarios" <?= $rel === 'operarios' ? 'selected' : '' ?>>Operarios × Áreas</option>
                    <option value="colores" <?= $rel === 'colores' ? 'selected' : '' ?>>Colores × Áreas</option>
                </select>
            </div>

            <?php if ($rel === 'referencias') { ?>
            <!-- Área a administrar -->
            <div class="grupo-campo">
                <label for="area">Área</label>
                <select id="area" name="area" onchange="this.form.submit()">
                    <option value="">Selecciona...</option>
                    <?php foreach ($areas as $area) { ?>
                        <option value="<?= $area['id_area'] ?>" <?= $idArea === (int) $area['id_area'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($area['nombre_area'])) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <?php } ?>

            <!-- Búsqueda por fila -->
            <div class="grupo-campo grupo-buscar">
                <label for="buscarMaquina">Buscar</label>
                <input type="text" id="buscarMaquina" autocomplete="off"
                    placeholder="<?= $rel === 'operarios' ? 'Buscar operario...' : ($rel === 'colores' ? 'Buscar color...' : 'Buscar máquina...') ?>">
            </div>
        </form>

        <!-- Exportar / importar relaciones -->
        <div class="acciones" id="accionesRelaciones" data-url="<?= $urlControlador ?>">
            <a class="btn btn-secundario" onclick="abrirModal('modalExportarRelaciones')">Exportar</a>
            <a class="btn btn-secundario" onclick="abrirModal('modalImportarRelaciones')">Importar</a>
        </div>

        <!-- Overlay: elegir qué exportar -->
        <div class="overlay" id="modalExportarRelaciones">
            <div class="modal modal-seleccion">
                <div class="modal-header">
                    <h2>Exportar relaciones</h2>
                    <button type="button" class="btn-cerrar-modal" onclick="cerrarModal('modalExportarRelaciones')">X</button>
                </div>
                <p class="texto-panel">Guardar el contenido actual de cada relación.</p>
                <form class="form-seleccion-relaciones" data-accion="exportar">
                    <div class="tabla-scroll">
                    <table class="tabla">
                        <thead>
                            <tr><th>Relación</th><th>Seleccionar</th></tr>
                        </thead>
                        <tbody>
                            <tr class="fila-todas">
                                <td>Todas</td>
                                <td><input type="checkbox" class="chk-todas-seleccion"></td>
                            </tr>
                            <tr><td>Máquinas × Áreas</td><td><input type="checkbox" name="tipos[]" value="areas"></td></tr>
                            <tr><td>Referencias × Máquinas</td><td><input type="checkbox" name="tipos[]" value="referencias"></td></tr>
                            <tr><td>Operarios × Áreas</td><td><input type="checkbox" name="tipos[]" value="operarios"></td></tr>
                            <tr><td>Colores × Áreas</td><td><input type="checkbox" name="tipos[]" value="colores"></td></tr>
                        </tbody>
                    </table>
                    </div>
                    <div class="acciones">
                        <button class="btn" type="submit">Exportar</button>
                        <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalExportarRelaciones')">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Overlay: elegir qué importar -->
        <div class="overlay" id="modalImportarRelaciones">
            <div class="modal modal-seleccion">
                <div class="modal-header">
                    <h2>Importar relaciones</h2>
                    <button type="button" class="btn-cerrar-modal" onclick="cerrarModal('modalImportarRelaciones')">X</button>
                </div>
                <p class="texto-panel">Agrega a cada relación su contenido guardado.</p>
                <form class="form-seleccion-relaciones" data-accion="importar">
                    <div class="tabla-scroll">
                    <table class="tabla">
                        <thead>
                            <tr><th>Relación</th><th>Seleccionar</th></tr>
                        </thead>
                        <tbody>
                            <tr class="fila-todas">
                                <td>Todas</td>
                                <td><input type="checkbox" class="chk-todas-seleccion"></td>
                            </tr>
                            <tr><td>Máquinas × Áreas</td><td><input type="checkbox" name="tipos[]" value="areas"></td></tr>
                            <tr><td>Referencias × Máquinas</td><td><input type="checkbox" name="tipos[]" value="referencias"></td></tr>
                            <tr><td>Operarios × Áreas</td><td><input type="checkbox" name="tipos[]" value="operarios"></td></tr>
                            <tr><td>Colores × Áreas</td><td><input type="checkbox" name="tipos[]" value="colores"></td></tr>
                        </tbody>
                    </table>
                    </div>
                    <div class="acciones">
                        <button class="btn" type="submit">Importar</button>
                        <button type="button" class="btn btn-secundario" onclick="cerrarModal('modalImportarRelaciones')">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($rel === 'areas') {
            $matriz = obtenerMatrizMaquinaAreas($conexion);
        ?>
        <!-- Matriz máquina × área -->
        <div class="matriz-scroll" data-url="<?= $urlControlador ?>">
            <table class="tabla tabla-matriz tabla-matriz-igual">
                <thead>
                    <tr>
                        <th class="col-matriz-nombre">Máquina</th>
                        <?php foreach ($matriz['areas'] as $area) { ?>
                            <th><?= htmlspecialchars(ucfirst($area['nombre_area'])) ?></th>
                        <?php } ?>
                        <th>Todas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matriz['maquinas'] as $maq) { ?>
                    <tr data-maquina-nombre="<?= htmlspecialchars(mb_strtolower($maq['nombre_maquina'])) ?>">
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
                        <td class="col-matriz-check">
                            <input type="checkbox" class="chk-todas" title="Marcar todas">
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php } elseif ($rel === 'operarios') {
            $matriz = obtenerMatrizOperarioAreas($conexion);
            // Incluye Peletizado, pero no Extrusión (usa OPERADORES, no OPERARIOS)
            $areasOperario = array_values(array_filter(areasOrdenadas($conexion), fn($a) => $a['nombre_area'] !== 'extrusion'));
        ?>
        <!-- Matriz operario × área -->
        <div class="matriz-scroll" data-url="<?= $urlControlador ?>">
            <table class="tabla tabla-matriz tabla-matriz-igual">
                <thead>
                    <tr>
                        <th class="col-matriz-nombre">Operario</th>
                        <?php foreach ($areasOperario as $area) { ?>
                            <th><?= htmlspecialchars(ucfirst($area['nombre_area'])) ?></th>
                        <?php } ?>
                        <th>Todas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matriz['operarios'] as $op) { ?>
                    <tr data-maquina-nombre="<?= htmlspecialchars(mb_strtolower($op['nombre_operario'])) ?>">
                        <td class="col-matriz-nombre"><?= htmlspecialchars($op['nombre_operario']) ?></td>
                        <?php foreach ($areasOperario as $area) { ?>
                        <td class="col-matriz-check">
                            <input type="checkbox" class="chk-matriz"
                                data-accion="toggle_operario_area"
                                data-operario="<?= $op['id_operario'] ?>"
                                data-area="<?= $area['id_area'] ?>"
                                <?= isset($matriz['relaciones'][$op['id_operario']][$area['id_area']]) ? 'checked' : '' ?>>
                        </td>
                        <?php } ?>
                        <td class="col-matriz-check">
                            <input type="checkbox" class="chk-todas" title="Marcar todas">
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php } elseif ($rel === 'colores') {
            $matriz = obtenerMatrizColorAreas($conexion);
            // Máquina Plana no maneja colores: no aparece en este selector
            $areasColor = array_values(array_filter(areasOrdenadas($conexion), fn($a) => $a['nombre_area'] !== 'plana'));
        ?>
        <!-- Matriz color × área -->
        <div class="matriz-scroll" data-url="<?= $urlControlador ?>">
            <table class="tabla tabla-matriz tabla-matriz-igual">
                <thead>
                    <tr>
                        <th class="col-matriz-nombre">Color</th>
                        <?php foreach ($areasColor as $area) { ?>
                            <th><?= htmlspecialchars(ucfirst($area['nombre_area'])) ?></th>
                        <?php } ?>
                        <th>Todas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matriz['colores'] as $col) { ?>
                    <tr data-maquina-nombre="<?= htmlspecialchars(mb_strtolower($col['nombre_color'])) ?>">
                        <td class="col-matriz-nombre"><?= htmlspecialchars($col['nombre_color']) ?></td>
                        <?php foreach ($areasColor as $area) { ?>
                        <td class="col-matriz-check">
                            <input type="checkbox" class="chk-matriz"
                                data-accion="toggle_color_area"
                                data-color="<?= $col['id_color'] ?>"
                                data-area="<?= $area['id_area'] ?>"
                                <?= isset($matriz['relaciones'][$col['id_color']][$area['id_area']]) ? 'checked' : '' ?>>
                        </td>
                        <?php } ?>
                        <td class="col-matriz-check">
                            <input type="checkbox" class="chk-todas" title="Marcar todas">
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php } elseif (!$idArea) { ?>
        <!-- Sin área seleccionada -->
        <p class="texto-panel">Selecciona un área arriba para ver sus máquinas y referencias.</p>

        <?php } else {
            $matriz = obtenerMatrizMaquinaReferencias($conexion, $idArea);
        ?>
        <!-- Matriz referencia × máquina (del área elegida) -->
        <div class="matriz-scroll" data-url="<?= $urlControlador ?>">
            <table class="tabla tabla-matriz tabla-matriz-igual">
                <thead>
                    <tr>
                        <th class="col-matriz-nombre">Máquina</th>
                        <th class="col-matriz-esp">Especiales</th>
                        <?php foreach ($matriz['referencias'] as $ref) { ?>
                            <th><?= htmlspecialchars($ref['nombre_referencia']) ?></th>
                        <?php } ?>
                        <th>Todas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$matriz['maquinas']) { ?>
                        <tr><td colspan="<?= count($matriz['referencias']) + 3 ?>">Esta área no tiene máquinas asignadas (ve a Máquinas × Áreas).</td></tr>
                    <?php } ?>
                    <?php foreach ($matriz['maquinas'] as $maq) { $esp = (bool) $maq['usa_referencias_esp']; ?>
                    <tr class="<?= $esp ? 'fila-usa-esp' : '' ?>" data-maquina-nombre="<?= htmlspecialchars(mb_strtolower($maq['nombre_maquina'])) ?>">
                        <td class="col-matriz-nombre"><?= htmlspecialchars($maq['nombre_maquina']) ?></td>
                        <td class="col-matriz-check col-matriz-esp">
                            <input type="checkbox" class="chk-matriz"
                                data-accion="toggle_maquina_esp"
                                data-maquina="<?= $maq['id_maquina'] ?>"
                                data-area="<?= $idArea ?>"
                                <?= $esp ? 'checked' : '' ?>>
                        </td>
                        <?php foreach ($matriz['referencias'] as $ref) { ?>
                        <td class="col-matriz-check">
                            <input type="checkbox" class="chk-matriz"
                                data-accion="toggle_maquina_referencia"
                                data-maquina="<?= $maq['id_maquina'] ?>"
                                data-area="<?= $idArea ?>"
                                data-referencia="<?= $ref['id_referencia'] ?>"
                                <?= isset($matriz['relaciones'][$maq['id_maquina']][$ref['id_referencia']]) ? 'checked' : '' ?>>
                        </td>
                        <?php } ?>
                        <td class="col-matriz-check">
                            <input type="checkbox" class="chk-todas" title="Marcar todas">
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>

    </div>

    <!-- Overlay de carga -->
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

    <script src="<?= BASE_URL ?>/modules/catalogs/scripts/relations.js"></script>

<?php include dirname(__DIR__, 3) . '/templates/footer.php'; ?>
