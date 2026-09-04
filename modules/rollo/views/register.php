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
    function opcionesCatalogoRollo($lista, $idKey, $nombreKey){
        $html = '<option value=""></option>';
        foreach($lista as $item){
            $html .= '<option value="' . $item[$idKey] . '">' . htmlspecialchars($item[$nombreKey]) . '</option>';
        }
        return $html;
    }
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/registerRollo.css">

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
                <select name="id_operario" id="operarioRollo" required>
                    <?= opcionesCatalogoRollo($operarios, 'id_operario', 'nombre_operario') ?>
                </select>
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

            <button type="submit" class="btn" id="btnRegistrarRollo">Registrar</button>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<script src="<?= BASE_URL ?>/modules/rollo/scripts/register.js"></script>

<?php include dirname(__DIR__, 3) . '/templates/footer.php'; ?>
