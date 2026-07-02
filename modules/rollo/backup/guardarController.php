<?php
/** @var mysqli $conexion */

require_once("../../includes/conexion.php");

$fecha = $_POST['fecha_roll'] ?? '';
$id_maquina = $_POST['id_maquina'] ?? '';
$id_turno = $_POST['id_turno'] ?? '';
$id_referencia = $_POST['id_referencia'] ?? '';
$id_color = $_POST['id_color'] ?? '';
$peso = $_POST['peso_rollo'] ?? 0;
$retal = $_POST['retal_roll'] ?? 0;
$total = $_POST['total_roll'] ?? 0;

$sql = "INSERT INTO PRODUCCION_ROLLO
(
    fecha_roll, 
    id_maquina, 
    id_turno, 
    id_referencia, 
    id_color, 
    peso_rollo, 
    retal_roll, 
    total_roll)
VALUES
(
    '$fecha',
    '$id_maquina',
    '$id_turno',
    '$id_referencia',
    '$id_color',
    '$peso',
    '$retal',
    '$total')";

mysqli_query($conexion,$sql);

header("Location: ../views/dashboard.php");
exit;