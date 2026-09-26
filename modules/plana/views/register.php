<?php
/** @var string $hoy */
/** @var array  $operarios */
/** @var array  $maquinas */
/** @var array  $referenciasEsp */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/controllers/registerController.php';
include dirname(__DIR__, 3) . '/templates/header.php';

// Opciones <option> de un catálogo
if(!function_exists('opcionesCatalogoPlana')){
    function opcionesCatalogoPlana($lista, $idKey, $nombreKey, $incluirVacio = true){
        $html = $incluirVacio ? '<option value=""></option>' : '';
        foreach($lista as $item){
            $html .= '<option value="' . $item[$idKey] . '">' . htmlspecialchars($item[$nombreKey]) . '</option>';
        }
        return $html;
    }
}

// Select de catálogo + Otro
if(!function_exists('campoConOtroPlana')){
    function campoConOtroPlana($lista, $idKey, $nombreKey, $id, $nombre){
        ob_start(); ?>
        <select name="<?= $nombre ?>" id="<?= $id ?>" class="tiene-otro" required>
            <option value=""></option>
            <option value="otro">Otro</option>
            <?= opcionesCatalogoPlana($lista, $idKey, $nombreKey, false) ?>
        </select>
        <input type="text" class="campo-libre" id="<?= $id ?>Texto" hidden autocomplete="off" placeholder="Escribe el nombre">
        <?php return ob_get_clean();
    }
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css">

<!-- Contenedor de Register Máquina Plana -->
<div class="container container-formulario" id="containerRegisterPlana">
    <h2 class="titulo-vista">Registro de Pesos · Máquina Plana</h2>

    <div class="card">
        <div class="aviso-toast" id="avisoPlana" hidden>
            <span class="aviso-toast-texto" id="avisoPlanaTexto"></span>
            <div class="aviso-toast-barra" id="avisoPlanaBarra"></div>
        </div>

        <form class="form-inicio" id="formRegistroPlana">
            <div class="campo">
                <label>Fecha</label>
                <input type="date" name="fecha" id="fechaPlana" value="<?= htmlspecialchars($hoy) ?>" required>
            </div>

            <div class="campo">
                <label>Operario</label>
                <?= campoConOtroPlana($operarios, 'id_operario', 'nombre_operario', 'operarioPlana', 'id_operario') ?>
            </div>

            <div class="campo">
                <label>Máquina</label>
                <select name="id_maquina" id="maquinaPlana" required>
                    <option value=""></option>
                    <?= opcionesCatalogoPlana($maquinas, 'id_maquina', 'nombre_maquina', false) ?>
                </select>
            </div>

            <div class="campo">
                <label>Referencia</label>
                <?= campoConOtroPlana($referenciasEsp, 'id', 'nombre', 'referenciaPlana', 'id_referencia') ?>
            </div>

            <div class="campo-doble">
                <div class="campo">
                    <label>Peso Rollo</label>
                    <input type="number" name="peso_rollo" id="pesoRolloPlana" min="0" step="0.01" placeholder="0">
                </div>
                <div class="campo">
                    <label>Peso Retal</label>
                    <input type="number" name="peso_retal" id="pesoRetalPlana" min="0" step="0.01" placeholder="0">
                </div>
            </div>

            <div class="campo-doble">
                <div class="campo">
                    <label>Bultos</label>
                    <input type="number" name="bultos" id="bultosPlana" min="0" step="1" inputmode="numeric" placeholder="0">
                </div>
                <div class="campo">
                    <label>Peso Total</label>
                    <input type="number" name="peso_total" id="pesoTotalPlana" min="0" step="0.01" placeholder="0">
                </div>
            </div>

            <div class="acciones-rollo">
                <a class="btn btn-cancelar" id="btnVolverPlana" href="<?= BASE_URL ?>/modules/plana/views/history.php">Volver</a>
                <button type="submit" class="btn" id="btnRegistrarPlana">Registrar</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<script src="<?= BASE_URL ?>/modules/shared/alertToast.js"></script>
<script src="<?= BASE_URL ?>/modules/plana/scripts/register.js"></script>

<?php include dirname(__DIR__, 3) . '/templates/footer.php'; ?>
