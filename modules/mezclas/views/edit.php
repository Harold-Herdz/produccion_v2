<?php
/** @var array $fila */
/** @var mysqli_result $operarios */
/** @var mysqli_result $maquinas */
/** @var mysqli_result $referencias */

// Importar authMiddleware.php
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar editController.php
require_once dirname(__DIR__) . '/controllers/editController.php';
// Importar header.php
include dirname(__DIR__, 3) . '/templates/header.php';

// Celda numérica de una tabla, con el valor ya guardado y la unidad al lado
if(!function_exists('celdaNumeroEditarMezcla')){
    function celdaNumeroEditarMezcla($id, $valor, $unidad){
        return '<div class="mez-celda"><input type="number" name="' . $id . '" min="0" step="any" placeholder="" value="' . htmlspecialchars((string) $valor) . '">'
            . '<span class="mez-unidad">' . $unidad . '</span></div>';
    }
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css">

<!-- Contenedor de Editar -->
<div class="container" id="containerEditar">
    <!-- Título -->
    <h2 class="titulo-vista">Editar Producción Mezclas</h2>

        <!-- Tarjeta -->
        <div class="card">

            <!-- Formulario de edición -->
            <form class="form-inicio" action="<?= BASE_URL ?>/modules/mezclas/controllers/historyController.php" method="POST">
            <!-- ID del registro a actualizar -->
            <input type="hidden" name="id" value="<?php echo $fila['id']; ?>">

            <!-- Fecha -->
            <div class="campo">
                <label>Fecha</label>
                <input
                    type="date"
                    name="fecha_mezcla"
                    value="<?php echo date('Y-m-d', strtotime($fila['fecha_mezcla'])); ?>">
            </div>

            <!-- Operario -->
            <div class="campo">
                <label>Operario</label>
                <select name="id_operario">
                    <?php while($o = mysqli_fetch_assoc($operarios)): ?>
                        <option
                            value="<?php echo $o['id_operario']; ?>"
                            <?php if($fila['id_operario'] == $o['id_operario']) echo "selected"; ?>>
                            <?php echo $o['nombre_operario']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Máquina -->
            <div class="campo">
                <label>Máquina</label>
                <select name="id_maquina">
                    <?php while($m = mysqli_fetch_assoc($maquinas)): ?>
                        <option
                            value="<?php echo $m['id_maquina']; ?>"
                            <?php if($fila['id_maquina'] == $m['id_maquina']) echo "selected"; ?>>
                            <?php echo $m['nombre_maquina']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Referencia -->
            <div class="campo">
                <label>Referencia</label>
                <select name="id_referencia">
                    <?php while($r = mysqli_fetch_assoc($referencias)): ?>
                        <option
                            value="<?php echo $r['id_referencia']; ?>"
                            <?php if($fila['id_referencia'] == $r['id_referencia']) echo "selected"; ?>>
                            <?php echo $r['nombre_referencia']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Observaciones -->
            <div class="campo">
                <label>Observaciones</label>
                <input type="text" name="observaciones" value="<?php echo htmlspecialchars((string) $fila['observaciones']); ?>">
            </div>

            <!-- Tabla 1 -->
            <div class="mez-tabla-wrap">
            <table class="ext-tabla mez-tabla">
                <thead>
                    <tr>
                        <th>BLANCO R</th><th>NEGRO R</th><th>ROJO R</th><th>AMARILLO R</th>
                        <th>AZUL R</th><th>VERDE R</th><th>NARANJA R</th><th>MARRÓN R</th><th>LADRILLO R</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= celdaNumeroEditarMezcla('blanco_r', $fila['blanco_r'], 'x25') ?></td>
                        <td><?= celdaNumeroEditarMezcla('negro_r', $fila['negro_r'], 'x25') ?></td>
                        <td><?= celdaNumeroEditarMezcla('rojo_r', $fila['rojo_r'], 'x25') ?></td>
                        <td><?= celdaNumeroEditarMezcla('amarillo_r', $fila['amarillo_r'], 'x25') ?></td>
                        <td><?= celdaNumeroEditarMezcla('azul_r', $fila['azul_r'], 'x25') ?></td>
                        <td><?= celdaNumeroEditarMezcla('verde_r', $fila['verde_r'], 'x25') ?></td>
                        <td><?= celdaNumeroEditarMezcla('naranja_r', $fila['naranja_r'], 'x25') ?></td>
                        <td><?= celdaNumeroEditarMezcla('marron_r', $fila['marron_r'], 'x25') ?></td>
                        <td><?= celdaNumeroEditarMezcla('ladrillo_r', $fila['ladrillo_r'], 'x25') ?></td>
                    </tr>
                </tbody>
            </table>
            </div>

            <!-- Tabla 2 -->
            <div class="mez-tabla-wrap">
            <table class="ext-tabla mez-tabla">
                <thead>
                    <tr>
                        <th>ORIGINAL B</th><th>FG</th><th>MASTER</th><th>LINEAL</th><th>DESHIDRATANTE</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= celdaNumeroEditarMezcla('original_b', $fila['original_b'], 'x25') ?></td>
                        <td><?= celdaNumeroEditarMezcla('fg', $fila['fg'], 'x25') ?></td>
                        <td><?= celdaNumeroEditarMezcla('master', $fila['master'], 'x25') ?></td>
                        <td><?= celdaNumeroEditarMezcla('lineal', $fila['lineal'], 'x25') ?></td>
                        <td><?= celdaNumeroEditarMezcla('deshidratante', $fila['deshidratante'], 'kg') ?></td>
                    </tr>
                </tbody>
            </table>
            </div>

            <!-- Tabla 3 -->
            <div class="mez-tabla-wrap">
            <table class="ext-tabla mez-tabla">
                <thead>
                    <tr>
                        <th>P. BLANCO</th><th>P. NEGRO</th><th>P. ROJO</th><th>P. AMARILLO</th>
                        <th>P. AZUL</th><th>P. VERDE</th><th>P. NARANJA</th>
                        <th>P. MARRÓN</th><th>P. LADRILLO</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= celdaNumeroEditarMezcla('p_blanco', $fila['p_blanco'], 'kg') ?></td>
                        <td><?= celdaNumeroEditarMezcla('p_negro', $fila['p_negro'], 'kg') ?></td>
                        <td><?= celdaNumeroEditarMezcla('p_rojo', $fila['p_rojo'], 'kg') ?></td>
                        <td><?= celdaNumeroEditarMezcla('p_amarillo', $fila['p_amarillo'], 'kg') ?></td>
                        <td><?= celdaNumeroEditarMezcla('p_azul', $fila['p_azul'], 'kg') ?></td>
                        <td><?= celdaNumeroEditarMezcla('p_verde', $fila['p_verde'], 'kg') ?></td>
                        <td><?= celdaNumeroEditarMezcla('p_naranja', $fila['p_naranja'], 'kg') ?></td>
                        <td><?= celdaNumeroEditarMezcla('p_marron', $fila['p_marron'], 'kg') ?></td>
                        <td><?= celdaNumeroEditarMezcla('p_ladrillo', $fila['p_ladrillo'], 'kg') ?></td>
                    </tr>
                </tbody>
            </table>
            </div>

            <!-- Botones -->
            <div class="acciones-editar">
                <button type="submit" class="btn" id="btnActualizar">Actualizar</button>
                <a class="btn btn-secundario" href="history.php">Cancelar</a>
            </div>
            </form>

        </div>

</div>

<?php
// Importar footer.php
include dirname(__DIR__, 3) . '/templates/footer.php';
?>
