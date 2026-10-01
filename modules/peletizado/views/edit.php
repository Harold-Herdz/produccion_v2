<?php
/** @var array $fila */
/** @var mysqli_result $maquinas */
/** @var mysqli_result $turnos */
/** @var mysqli_result $operarios */
/** @var mysqli_result $operarios2 */
/** @var mysqli_result $colores */

// Importar authMiddleware.php
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar editController.php
require_once dirname(__DIR__) . '/controllers/editController.php';
// Importar header.php
include dirname(__DIR__, 3) . '/templates/header.php';
?>

<!-- Contenedor de Editar -->
<div class="container" id="containerEditar">
    <!-- Título -->
    <h2 class="titulo-vista">Editar Producción Peletizado</h2>

        <!-- Tarjeta -->
        <div class="card">

            <!-- Formulario de edición -->
            <form action="<?= BASE_URL ?>/modules/peletizado/controllers/historyController.php" method="POST">
            <!-- ID del registro a actualizar -->
            <input type="hidden" name="id" value="<?php echo $fila['id']; ?>">

            <!-- Fecha -->
            <label>Fecha</label>
            <input
                type="date"
                name="fecha_peletizado"
                value="<?php echo date('Y-m-d', strtotime($fila['fecha_peletizado'])); ?>" required>

            <!-- Máquina -->
            <label>Máquina</label>
            <select name="id_maquina" required>
                <?php while($m = mysqli_fetch_assoc($maquinas)): ?>
                    <option
                        value="<?php echo $m['id_maquina']; ?>"
                        <?php if($fila['id_maquina'] == $m['id_maquina']) echo 'selected'; ?>>
                        <?php echo $m['nombre_maquina']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Turno -->
            <label>Turno</label>
            <select name="id_turno" required>
                <?php while($t = mysqli_fetch_assoc($turnos)): ?>
                    <option
                        value="<?php echo $t['id_turno']; ?>"
                        <?php if($fila['id_turno'] == $t['id_turno']) echo 'selected'; ?>>
                        <?php echo $t['nombre_turno']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Operario 1 -->
            <label>Operario 1</label>
            <select name="id_operario" required>
                <?php while($o = mysqli_fetch_assoc($operarios)): ?>
                    <option
                        value="<?php echo $o['id_operario']; ?>"
                        <?php if($fila['id_operario'] == $o['id_operario']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($o['nombre_operario']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Operario 2 (opcional, mismo catálogo) -->
            <label>Operario 2</label>
            <select name="id_operario2">
                <option value=""></option>
                <?php while($o = mysqli_fetch_assoc($operarios2)): ?>
                    <option
                        value="<?php echo $o['id_operario']; ?>"
                        <?php if($fila['id_operario2'] !== null && $fila['id_operario2'] == $o['id_operario']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($o['nombre_operario']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Color (opcional) -->
            <label>Color</label>
            <select name="id_color">
                <option value=""></option>
                <?php while($c = mysqli_fetch_assoc($colores)): ?>
                    <option
                        value="<?php echo $c['id_color']; ?>"
                        <?php if($fila['id_color'] !== null && $fila['id_color'] == $c['id_color']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($c['nombre_color']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Alta retal -->
            <label>Alta retal</label>
            <input type="number" name="alta_retal" value="<?php echo $fila['alta_retal']; ?>">

            <!-- Baja -->
            <label>Baja</label>
            <input type="number" name="baja" value="<?php echo $fila['baja']; ?>">

            <!-- Refiltrado -->
            <label>Refiltrado</label>
            <input type="number" name="refiltrado" value="<?php echo $fila['refiltrado']; ?>">

            <!-- Soplado -->
            <label>Soplado</label>
            <input type="number" name="soplado" value="<?php echo $fila['soplado']; ?>">

            <!-- Torta -->
            <label>Torta</label>
            <input type="number" name="torta" value="<?php echo $fila['torta']; ?>">

            <!-- Limpieza -->
            <label>Limpieza</label>
            <input type="number" name="limpieza" value="<?php echo $fila['limpieza']; ?>">

            <!-- Total -->
            <label>Total</label>
            <input type="number" name="total" value="<?php echo $fila['total']; ?>">

            <!-- Observaciones -->
            <label>Observaciones</label>
            <textarea name="obs_peletizado"><?php echo htmlspecialchars((string) $fila['obs_peletizado']); ?></textarea>

            <!-- Botones -->
            <div class="acciones-editar">
                <button type="submit" class="btn" id="btnActualizar">Actualizar</button>
                <a class="btn btn-secundario" href="history.php">Cancelar</a>
            </div>
            </form>

        </div>

    <br><br>

    <!-- Botones de navegación -->
</div>

<?php
// Importar footer.php
include dirname(__DIR__, 3) . '/templates/footer.php';
?>
