<?php 
/** @var mysqli $conexion */

include("../templates/header.php");
require_once("../conexion.php");

/* CARGAR DATOS PARA SELECTS */

$maquinas = mysqli_query($conexion,"SELECT * FROM MAQUINAS");
$turnos = mysqli_query($conexion,"SELECT * FROM TURNOS");
$referencias = mysqli_query($conexion,"SELECT * FROM REFERENCIAS");
$colores = mysqli_query($conexion,"SELECT * FROM COLORES");
?>

<div class="container">

<h2 class="titulo-vista">Registrar Producción por Rollo</h2>

<div class="card">

<form action="guardar.php" method="POST">

<label>Fecha</label>
<input type="datetime-local" name="fecha_roll" required>

<label>Máquina</label>
<select name="id_maquina" required>
<option value="">Seleccionar</option>
<?php while($m = mysqli_fetch_assoc($maquinas)){ ?>
<option value="<?php echo $m['id_maquina']; ?>">
<?php echo $m['nombre_maquina']; ?>
</option>
<?php } ?>
</select>

<label>Turno</label>
<select name="id_turno" required>
<option value="">Seleccionar</option>
<?php while($t = mysqli_fetch_assoc($turnos)){ ?>
<option value="<?php echo $t['id_turno']; ?>">
<?php echo $t['nombre_turno']; ?>
</option>
<?php } ?>
</select>

<label>Referencia</label>
<select name="id_referencia" required>
<option value="">Seleccionar</option>
<?php while($r = mysqli_fetch_assoc($referencias)){ ?>
<option value="<?php echo $r['id_referencia']; ?>">
<?php echo $r['nombre_referencia']; ?>
</option>
<?php } ?>
</select>

<label>Color</label>
<select name="id_color" required>
<option value="">Seleccionar</option>
<?php while($c = mysqli_fetch_assoc($colores)){ ?>
<option value="<?php echo $c['id_color']; ?>">
<?php echo $c['nombre_color']; ?>
</option>
<?php } ?>
</select>

<label>Peso (kg)</label>
<input type="number" name="peso_rollo" step="any" class="form-control" required>

<label>Retal (kg)</label>
<input type="number" name="retal_roll" step="any" class="form-control" required>

<label>Peso Total (kg)</label>
<input type="number" name="total_roll" step="any" class="form-control" required>

<button class="btn" type="submit">Guardar</button>

</form>

</div>

<div class="btn-group">

<a class="btn" href="dashboard.php">Volver al dashboard</a>

<a class="btn" href="../index.php">Volver al menú</a>

</div>

</div>

<?php include("../includes/footer.php"); ?>
