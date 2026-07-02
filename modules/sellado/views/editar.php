<?php
/** @var array $fila */
/** @var mysqli_result $operarios */
/** @var mysqli_result $maquinas */
/** @var mysqli_result $referencias */
/** @var mysqli_result $colores */
/** @var mysqli_result $turnos */

// Importar proteger.php
require_once dirname(__DIR__, 3) . '/auth/proteger.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar editarController.php
require_once dirname(__DIR__) . '/controllers/editarController.php';
// Importar header.php
include dirname(__DIR__, 3) . '/templates/header.php';
?>

<!-- Contenedor de Editar -->
<div class="container" id="containerEditar">
    <!-- Título -->
    <h2 class="titulo-vista">Editar Producción Sellado</h2>

        <!-- Tarjeta -->
        <div class="card">

            <!-- Formulario de edición -->
            <form action="<?= BASE_URL ?>/modules/sellado/controllers/actualizarController.php" method="POST">
            <!-- ID del registro a actualizar -->
            <input type="hidden" name="id" value="<?php echo $fila['id']; ?>">

            <!-- Fecha -->
            <label>Fecha</label>
                <input 
                    type="datetime-local"
                    name="fecha_paq"
                    value="<?php echo date('Y-m-d\TH:i', strtotime($fila['fecha_paq'])); ?>">

            <!-- Máquina -->
            <label>Máquina</label>
            <select name="id_maquina">
                <?php while($m = mysqli_fetch_assoc($maquinas)): ?>
                    <option
                        value="<?php echo $m['id_maquina']; ?>"
                        <?php echo ($m['id_maquina'] == $fila['id_maquina']) ? 'selected' : ''; ?>>
                        <?php echo $m['nombre_maquina']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Operario -->
            <label>Operario</label>
            <select name="id_operario">
                <?php while($op = mysqli_fetch_assoc($operarios)): ?>
                    <option 
                        value="<?php echo $op['id_operario']; ?>"
                        <?php echo ($op['id_operario'] == $fila['id_operario']) ? 'selected' : ''; ?>>
                        <?php echo $op['nombre_operario']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Turno -->
            <label>Turno</label>
            <select name="id_turno">
                <?php while($t = mysqli_fetch_assoc($turnos)): ?>
                    <option
                        value="<?php echo $t['id_turno']; ?>"
                        <?php echo ($t['id_turno'] == $fila['id_turno']) ? 'selected' : ''; ?>>
                        <?php echo $t['nombre_turno']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Referencia -->
            <label>Referencia</label>
            <select name="id_referencia">
                <?php while($r = mysqli_fetch_assoc($referencias)): ?>
                    <option
                        value="<?php echo $r['id_referencia']; ?>"
                        <?php echo ($r['id_referencia'] == $fila['id_referencia']) ? 'selected' : ''; ?>>
                        <?php echo $r['nombre_referencia']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Color -->
            <label>Color</label>
            <select name="id_color">
                <?php while($c = mysqli_fetch_assoc($colores)): ?>
                    <option
                        value="<?php echo $c['id_color']; ?>"
                        <?php echo ($c['id_color'] == $fila['id_color']) ? 'selected' : ''; ?>>
                        <?php echo $c['nombre_color']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Cantidad de Paquetes x70 -->
            <label>X70</label>
            <input
                type="number"
                name="paquetes_x70"
                value="<?php echo $fila['paquetes_x70']; ?>">

            <!-- Cantidad de Paquetes x90 -->
            <label>X90</label>
            <input
                type="number"
                name="paquetes_x90"
                value="<?php echo $fila['paquetes_x90']; ?>">

            <!-- Cantidad de Paquetes x98 -->
            <label>X98</label>
            <input
                type="number"
                name="paquetes_x98"
                value="<?php echo $fila['paquetes_x98']; ?>">

            <!-- Total de Paquetes -->
            <label>Total</label>
            <input
                type="number"
                name="paquetes_total"
                value="<?php echo $fila['paquetes_total']; ?>">

            <!-- Observaciones -->
            <label>Observaciones</label>
            <textarea name="observaciones_paq"><?php echo $fila['observaciones_paq']; ?></textarea>

            <!-- Botón de Actualizar -->
            <button type="submit" class="btn" id="btnActualizar">Actualizar</button>
            </form>

        </div>
    
    <br><br>
    
    <!-- Botones de navegación -->
    <div class="acciones">
        <a class="btn" href="lista.php">Volver al Historial</a>
        <a class="btn" href="dashboard.php">Volver al Dashboard</a>
    </div>
</div>

<?php 
// Importar footer.php
include dirname(__DIR__, 3) . '/templates/footer.php';
?>