<?php
/** @var array $fila */
/** @var mysqli_result $maquinas */
/** @var mysqli_result $operarios */
/** @var mysqli_result $referencias */

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
    <h2 class="titulo-vista">Editar Producción Máquina Plana</h2>
        
        <!-- Tarjeta -->
        <div class="card">

            <!-- Formulario de edición -->
            <form action="<?= BASE_URL ?>/modules/plana/controllers/historyController.php" method="POST">
            <!-- ID del registro a actualizar -->
            <input type="hidden" name="id" value="<?php echo $fila['id']; ?>">

            <!-- Fecha -->
            <label>Fecha</label>
            <input          
                type="date" 
                name="fecha_plana" 
                value="<?php echo date('Y-m-d', strtotime($fila['fecha_plana'])); ?>" required>

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

            <!-- Referencia especial -->
            <label>Referencia</label>
                <select name="id_referencia_esp" required>
                <?php while($r = mysqli_fetch_assoc($referencias)): ?>
                    <option
                        value="<?php echo $r['id_referencia_esp']; ?>"
                        <?php if($fila['id_referencia_esp'] == $r['id_referencia_esp']) echo 'selected'; ?>>
                        <?php echo $r['nombre_referencia_esp']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Peso del rollo -->
            <label>Peso Rollo (kg)</label>
            <input
                type="number"
                name="peso_rollo"
                value="<?php echo $fila['peso_rollo']; ?>">

            <!-- Peso del retal -->
            <label>Peso Retal (kg)</label>
            <input
                type="number"
                name="peso_retal"
                value="<?php echo $fila['peso_retal']; ?>">

            <!-- Cantidad de Bultos -->
            <label>Bultos</label>
            <input
                type="number"
                name="bultos"
                value="<?php echo $fila['bultos']; ?>">

            <!-- Peso Total -->
            <label>Peso Total (kg)</label>
            <input
                type="number"
                name="peso_total"
                value="<?php echo $fila['peso_total']; ?>">

            <!-- Botón de Actualizar -->
            <button type="submit" class="btn" id="btnActualizar">Actualizar</button>
            </form>

        </div>

    <br><br>

    <!-- Botones de navegación -->
</div>

<?php 
// Importar footer.php
include dirname(__DIR__, 3) . '/templates/footer.php';
?>