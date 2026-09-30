<?php
/** @var array $fila */
/** @var mysqli_result $maquinas */
/** @var mysqli_result $turnos */
/** @var mysqli_result $operadores */
/** @var mysqli_result $referencias */
/** @var mysqli_result $colores */
/** @var mysqli_result $laminas */

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
    <h2 class="titulo-vista">Editar Producción Extrusión</h2>
        
        <!-- Tarjeta -->
        <div class="card">

            <!-- Formulario de edición -->
            <form action="<?= BASE_URL ?>/modules/extrusion/controllers/historyController.php" method="POST">
            <!-- ID del registro a actualizar -->
            <input type="hidden" name="id" value="<?php echo $fila['id']; ?>">

            <!-- Fecha -->
            <label>Fecha</label>
            <input 
                type="date" 
                name="fecha_extrusion" 
                value="<?php echo date('Y-m-d', strtotime($fila['fecha_extrusion'])); ?>" required>

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

            <!-- Operador -->
            <label>Operador</label>
            <select name="id_operador" required>
                <?php while($o = mysqli_fetch_assoc($operadores)): ?>
                    <option
                        value="<?php echo $o['id_operador']; ?>"
                        <?php if($fila['id_operador'] == $o['id_operador']) echo 'selected'; ?>>
                        <?php echo $o['nombre_operador']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Referencia (catálogo K o especial) -->
            <label>Referencia</label>
                <select name="referencia_valor" required>
                <option value=""></option>
                <optgroup label="Referencias">
                <?php while($r = mysqli_fetch_assoc($referencias)): ?>
                    <option
                        value="r:<?php echo $r['id_referencia']; ?>"
                        <?php if($fila['id_referencia'] !== null && $fila['id_referencia'] == $r['id_referencia']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($r['nombre_referencia']); ?>
                    </option>
                <?php endwhile; ?>
                </optgroup>
                <optgroup label="Referencias especiales">
                <?php while($r = mysqli_fetch_assoc($referenciasEsp)): ?>
                    <option
                        value="e:<?php echo $r['id_referencia_esp']; ?>"
                        <?php if($fila['id_referencia_esp'] !== null && $fila['id_referencia_esp'] == $r['id_referencia_esp']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($r['nombre_referencia_esp']); ?>
                    </option>
                <?php endwhile; ?>
                </optgroup>
            </select>

            <!-- Color -->
            <label>Color</label>
                <select name="id_color" required>
                <?php while($c = mysqli_fetch_assoc($colores)): ?>
                    <option 
                        value="<?php echo $c['id_color']; ?>" 
                        <?php if($fila['id_color'] == $c['id_color']) echo 'selected'; ?>>
                        <?php echo $c['nombre_color']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Lamina P (opcional) -->
            <label>Lamina P</label>
            <select name="id_lamina_p">
                <option value=""></option>
                <?php while($l = mysqli_fetch_assoc($laminas)): ?>
                    <option
                        value="<?php echo $l['id_lamina_p']; ?>"
                        <?php if($fila['id_lamina_p'] !== null && $fila['id_lamina_p'] == $l['id_lamina_p']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($l['nombre_lamina_p']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Rollos -->
            <label>Rollos</label>
            <input 
                type="number" 
                name="rollos" 
                value="<?php echo $fila['rollos']; ?>">

            <!-- Peso Total -->
            <label>Peso Total (kg)</label>
            <input 
                type="number" 
                name="peso_total" 
                value="<?php echo $fila['peso_total']; ?>">

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