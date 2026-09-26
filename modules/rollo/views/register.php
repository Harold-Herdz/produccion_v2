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

// Select de catálogo + "Otro". En Operario aparece una casilla nueva al lado;
// en Referencia/Color la casilla reemplaza al select (ver register.js)
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
<div class="container container-formulario" id="containerRegisterRollo">
    <h2 class="titulo-vista">Registro de Pesos · Rollos</h2>

    <div class="card">
        <div class="aviso-toast" id="avisoRollo" hidden>
            <span class="aviso-toast-texto" id="avisoRolloTexto"></span>
            <div class="aviso-toast-barra" id="avisoRolloBarra"></div>
        </div>

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
                    <option value=""></option>
                    <?= opcionesCatalogoRollo($maquinas, 'id_maquina', 'nombre_maquina', false) ?>
                </select>
            </div>

            <div class="campo">
                <label>Referencia</label>
                <?= campoConOtroRollo([], 'id', 'nombre', 'referenciaRollo', 'id_referencia') ?>
            </div>

            <div class="campo">
                <label>Color</label>
                <?= campoConOtroRollo($colores, 'id_color', 'nombre_color', 'colorRollo', 'id_color') ?>
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
                <a class="btn btn-cancelar" id="btnVolverRollo" href="<?= BASE_URL ?>/modules/rollo/views/history.php">Volver</a>
                <button type="submit" class="btn" id="btnRegistrarRollo">Registrar</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<script src="<?= BASE_URL ?>/modules/shared/alertToast.js"></script>
<script>const mapaReferenciasMaquina = <?= json_encode($mapaReferenciasMaquina, JSON_HEX_TAG) ?>;</script>
<script src="<?= BASE_URL ?>/modules/rollo/scripts/register.js"></script>

<?php include dirname(__DIR__, 3) . '/templates/footer.php'; ?>
