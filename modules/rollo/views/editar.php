<?php
/** @var array $fila */
/** @var mysqli_result $maquinas */
/** @var mysqli_result $turnos */
/** @var mysqli_result $referencias */
/** @var mysqli_result $colores */

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
    <h2 class="titulo-vista">Editar Producción Rollo</h2>

        <!-- Tarjeta -->
        <div class="card">

            <!-- Formulario de edición -->
            <form action="<?= BASE_URL ?>/modules/rollo/controllers/actualizarController.php" method="POST">
            <!-- ID del registro a actualizar -->
            <input type="hidden" name="id" value="<?php echo $fila['id']; ?>">
                      
            <!-- Fecha -->
            <label>Fecha</label>
            <input 
                type="datetime-local" 
                name="fecha_roll"
                value="<?php echo date('Y-m-d\TH:i', strtotime($fila['fecha_roll'])); ?>">

            <!-- Máquina -->
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

            <!-- Referencia -->
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

            <!-- Color -->
            <label>Color</label>
            <select name="id_color">
                <?php while($c = mysqli_fetch_assoc($colores)): ?>
                    <option 
                        value="<?php echo $c['id_color']; ?>"
                        <?php if($fila['id_color'] == $c['id_color']) echo "selected"; ?>>
                        <?php echo $c['nombre_color']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Peso del Producido -->
            <label>Peso (kg)</label>
            <input 
                type="number"
                name="peso_rollo" 
                step="any" 
                class="form-control" 
                value="<?php echo $fila['peso_rollo']; ?>">

            <!-- Peso del Retal -->
            <label>Retal (kg)</label>
            <input 
                type="number" 
                name="retal_roll" 
                step="any" 
                class="form-control" 
                value="<?php echo $fila['retal_roll']; ?>">

            <!-- Peso Total -->
            <label>Total (kg)</label>
            <input 
                type="number" 
                name="total_roll" 
                step="any" 
                class="form-control" 
                value="<?php echo $fila['total_roll']; ?>">
        
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