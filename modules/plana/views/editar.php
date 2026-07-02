<?php
/** @var array $fila */
/** @var mysqli_result $maquinas */
/** @var mysqli_result $turnos */
/** @var mysqli_result $operarios */
/** @var mysqli_result $referencias */

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
    <h2 class="titulo-vista">Editar Producción Máquina Plana</h2>
        
        <!-- Tarjeta -->
        <div class="card">

            <!-- Formulario de edición -->
            <form action="<?= BASE_URL ?>/modules/plana/controllers/actualizarController.php" method="POST">
            <!-- ID del registro a actualizar -->
            <input type="hidden" name="id" value="<?php echo $fila['id']; ?>">

            <!-- Fecha -->
            <label>Fecha</label>
            <input 
                type="datetime-local" 
                name="fecha_plana" 
                value="<?php echo date('Y-m-d\TH:i', strtotime($fila['fecha_plana'])); ?>" required>

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

            <!-- Operario -->
            <label>Operario</label>
            <select name="id_operario" required>
                <?php while($o = mysqli_fetch_assoc($operarios)): ?>
                    <option 
                        value="<?php echo $o['id_operario']; ?>" 
                        <?php if($fila['id_operario'] == $o['id_operario']) echo 'selected'; ?>>
                        <?php echo $o['nombre_operario']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Referencia -->
            <label>Referencia</label>
                <select name="id_referencia" required>
                <?php while($r = mysqli_fetch_assoc($referencias)): ?>
                    <option 
                        value="<?php echo $r['id_referencia']; ?>" 
                        <?php if($fila['id_referencia'] == $r['id_referencia']) echo 'selected'; ?>>
                        <?php echo $r['nombre_referencia']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Peso del Producido -->
            <label>Peso (kg)</label>
            <input 
                type="number" 
                name="peso_plana" 
                value="<?php echo $fila['peso_plana']; ?>" required>

            <!-- Cantidad de Bultos -->
            <label>Bultos</label>
            <input 
                type="number" 
                name="bultos_plana" 
                value="<?php echo $fila['bultos_plana']; ?>">

            <!-- Peso del Retal -->
            <label>Retal (kg)</label>
            <input 
                type="number" 
                name="retal_plana" 
                value="<?php echo $fila['retal_plana']; ?>">

            <!-- Peso Total -->
            <label>Total (kg)</label>
            <input 
                type="number" 
                name="total_plana" 
                value="<?php echo $fila['total_plana']; ?>" required>

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