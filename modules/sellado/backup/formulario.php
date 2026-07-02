<?php 
/** @var mysqli $conexion */

include("../includes/header.php"); 
require_once("../includes/conexion.php");
?>

<div class="container">

<h2 class="titulo-vista">Registrar Producción Paquetes</h2>

<div class="card">

<form action="guardar.php" method="POST">

<label>Fecha</label>
<input type="datetime-local" name="fecha_paq" required>

<label>Operario</label>
<select name="id_operario" required>
<option value="">Seleccione</option>
<?php
$res = mysqli_query($conexion,"SELECT * FROM OPERARIOS");
while($op = mysqli_fetch_assoc($res)){
?>
<option value="<?php echo $op['id_operario']; ?>">
<?php echo $op['nombre']; ?>
</option>
<?php } ?>
</select>

<label>Máquina</label>
<select name="id_maquina" required>
<option value="">Seleccione</option>
<?php
$res = mysqli_query($conexion,"SELECT * FROM MAQUINAS");
while($m = mysqli_fetch_assoc($res)){
?>
<option value="<?php echo $m['id_maquina']; ?>">
<?php echo $m['nombre_maquina']; ?>
</option>
<?php } ?>
</select>

<label>Referencia</label>
<select name="id_referencia" required>
<option value="">Seleccione</option>
<?php
$res = mysqli_query($conexion,"SELECT * FROM REFERENCIAS");
while($r = mysqli_fetch_assoc($res)){
?>
<option value="<?php echo $r['id_referencia']; ?>">
<?php echo $r['nombre_referencia']; ?>
</option>
<?php } ?>
</select>

<label>Turno</label>
<select name="id_turno" required>
<option value="">Seleccione</option>
<?php
$res = mysqli_query($conexion,"SELECT * FROM TURNOS");
while($t = mysqli_fetch_assoc($res)){
?>
<option value="<?php echo $t['id_turno']; ?>">
<?php echo $t['nombre_turno']; ?>
</option>
<?php } ?>
</select>

<label>Color</label>
<select name="id_color" required>
<option value="">Seleccione</option>
<?php
$res = mysqli_query($conexion,"SELECT * FROM COLORES");
while($c = mysqli_fetch_assoc($res)){
?>
<option value="<?php echo $c['id_color']; ?>">
<?php echo $c['nombre_color']; ?>
</option>
<?php } ?>
</select>

<label>Paquetes</label>
<input type="number" name="paquetes_paq" required>

<label>Observación</label>
<textarea name="observaciones_paq"></textarea>

<button class="btn">Guardar</button>

</form>

</div>

<div class="btn-group">

<a class="btn" href="dashboard.php">Volver al dashboard</a>

<a class="btn" href="../index.php">Volver al menú</a>

</div>

</div>

<?php include("../includes/footer.php"); ?>
