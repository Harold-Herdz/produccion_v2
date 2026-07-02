<?php
/** @var mysqli $conexion */

require_once("../../includes/conexion.php");

$fecha = $_POST['fecha_paq'];
$id_operario = $_POST['id_operario'];
$id_maquina = $_POST['id_maquina'];
$id_referencia = $_POST['id_referencia'];
$id_turno = $_POST['id_turno'];
$id_color = $_POST['id_color'];
$paquetes = $_POST['paquetes_paq'];
$observaciones = $_POST['observaciones_paq'];

$sql = "INSERT INTO PRODUCCION_PAQUETES
(
    fecha_paq,
    id_operario,
    id_maquina,
    id_referencia,
    id_turno,
    id_color,
    paquetes_paq,
    observaciones_paq
)
VALUES
(
    '$fecha',
    '$id_operario',
    '$id_maquina',
    '$id_referencia',
    '$id_turno',
    '$id_color',
    '$paquetes',
    '$observaciones'
)";

mysqli_query($conexion,$sql);

header("Location: ../views/dashboard.php");
exit;