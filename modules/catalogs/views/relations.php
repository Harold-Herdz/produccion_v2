<?php
/** @var mysqli $conexion */
/** @var string $rel Relación activa: areas | referencias */

// Restringir acceso solo a administradores
$soloAdmin = true;
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Prepara $rel y atiende POST
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
                    <option value="referencias" <?= $rel === 'referencias' ? 'selected' : '' ?>>Máquinas × Referencias</option>
                </select>
            </div>

            <!-- Búsqueda por máquina -->
            <div class="grupo-campo">
                <label for="buscarMaquina">Buscar</label>
                <input type="text" id="buscarMaquina" autocomplete="off" placeholder="Buscar máquina...">
            </div>
        </form>

        <!-- Exportar / importar relaciones -->
        <div class="acciones" id="accionesRelaciones" data-url="<?= $urlControlador ?>">
            <a class="btn btn-secundario" onclick="accionRelaciones('exportar')">Exportar</a>
            <a class="btn btn-secundario" onclick="accionRelaciones('importar')">Importar</a>
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
                        <th>Todas</th>
                        <?php foreach ($matriz['areas'] as $area) { ?>
                            <th><?= htmlspecialchars(ucfirst($area['nombre_area'])) ?></th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matriz['maquinas'] as $maq) { ?>
                    <tr data-maquina-nombre="<?= htmlspecialchars(mb_strtolower($maq['nombre_maquina'])) ?>">
                        <td class="col-matriz-nombre"><?= htmlspecialchars($maq['nombre_maquina']) ?></td>
                        <td class="col-matriz-check">
                            <input type="checkbox" class="chk-todas" title="Marcar todas">
                        </td>
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

        <?php } else {
            $matriz = obtenerMatrizMaquinaReferencias($conexion);
        ?>
        <!-- Matriz máquina × referencia -->
        <div class="matriz-scroll" data-url="<?= $urlControlador ?>">
            <table class="tabla tabla-matriz tabla-matriz-igual">
                <thead>
                    <tr>
                        <th class="col-matriz-nombre">Máquina</th>
                        <th class="col-matriz-esp">Especiales</th>
                        <th>Todas</th>
                        <?php foreach ($matriz['referencias'] as $ref) { ?>
                            <th><?= htmlspecialchars($ref['nombre_referencia']) ?></th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matriz['maquinas'] as $maq) { $esp = (bool) $maq['usa_referencias_esp']; ?>
                    <tr class="<?= $esp ? 'fila-usa-esp' : '' ?>" data-maquina-nombre="<?= htmlspecialchars(mb_strtolower($maq['nombre_maquina'])) ?>">
                        <td class="col-matriz-nombre"><?= htmlspecialchars($maq['nombre_maquina']) ?></td>
                        <td class="col-matriz-check col-matriz-esp">
                            <input type="checkbox" class="chk-matriz"
                                data-accion="toggle_maquina_esp"
                                data-maquina="<?= $maq['id_maquina'] ?>"
                                <?= $esp ? 'checked' : '' ?>>
                        </td>
                        <td class="col-matriz-check">
                            <input type="checkbox" class="chk-todas" title="Marcar todas" <?= $esp ? 'disabled' : '' ?>>
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
