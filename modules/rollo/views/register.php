<?php
/** @var string $hoy */
/** @var array  $operarios */
/** @var array  $maquinas */
/** @var array  $referencias */
/** @var array  $colores */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/controllers/registerController.php';
include dirname(__DIR__, 3) . '/templates/header.php';

// Opciones <option> de un catálogo
if(!function_exists('opcionesCatalogoRollo')){
    function opcionesCatalogoRollo($lista, $idKey, $nombreKey, $incluirVacio = true){
        $html = $incluirVacio ? '<option value=""></option>' : '';
        foreach($lista as $item){
            $html .= '<option value="' . $item[$idKey] . '">' . htmlspecialchars($item[$nombreKey]) . '</option>';
        }
        return $html;
    }
}

// Select de catálogo + "Otro" (al elegirlo, el mismo campo se vuelve texto libre)
if(!function_exists('campoConOtroRollo')){
    function campoConOtroRollo($lista, $idKey, $nombreKey, $id, $nombre){
        ob_start(); ?>
        <select name="<?= $nombre ?>" id="<?= $id ?>" class="tiene-otro" required>
            <option value=""></option>
            <option value="otro">Otro</option>
            <?= opcionesCatalogoRollo($lista, $idKey, $nombreKey, false) ?>
        </select>
        <input type="text" class="campo-libre" id="<?= $id ?>Texto" hidden autocomplete="off" placeholder="Escribe el nombre">
        <?php return ob_get_clean();
    }
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css">

<!-- Contenedor de Register Rollos -->
<div class="container" id="containerRegisterRollo">
    <h2 class="titulo-vista">Registro de Producción · Rollos</h2>

    <div class="card">
        <p class="aviso" id="avisoRollo" hidden></p>

        <form class="form-inicio" id="formRegistroRollo">
            <div class="campo">
                <label>Fecha</label>
                <input type="date" name="fecha" id="fechaRollo" value="<?= htmlspecialchars($hoy) ?>" required>
            </div>

            <div class="campo">
                <label>Operario</label>
                <?= campoConOtroRollo($operarios, 'id_operario', 'nombre_operario', 'operarioRollo', 'id_operario') ?>
            </div>

            <div class="campo">
                <label>Máquina</label>
                <select name="id_maquina" id="maquinaRollo" required>
                    <?= opcionesCatalogoRollo($maquinas, 'id_maquina', 'nombre_maquina') ?>
                </select>
            </div>

            <div class="campo">
                <label>Referencia</label>
                <select name="id_referencia" id="referenciaRollo" required>
                    <?= opcionesCatalogoRollo($referencias, 'id_referencia', 'nombre_referencia') ?>
                </select>
            </div>

            <div class="campo">
                <label>Color</label>
                <select name="id_color" id="colorRollo" required>
                    <?= opcionesCatalogoRollo($colores, 'id_color', 'nombre_color') ?>
                </select>
            </div>

            <div class="campo-doble">
                <div class="campo">
                    <label>Peso Rollo</label>
                    <input type="number" name="peso_rollo" id="pesoRolloInput" min="0" step="0.01" placeholder="0">
                </div>
                <div class="campo">
                    <label>Peso Retal</label>
                    <input type="number" name="peso_retal" id="pesoRetalInput" min="0" step="0.01" placeholder="0">
                </div>
            </div>

            <div class="acciones-rollo">
                <a class="btn btn-cancelar" href="<?= BASE_URL ?>/modules/rollo/views/history.php">Volver</a>
                <button type="submit" class="btn" id="btnRegistrarRollo">Registrar</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<script src="<?= BASE_URL ?>/modules/rollo/scripts/register.js"></script>

<?php include dirname(__DIR__, 3) . '/templates/footer.php'; ?>
