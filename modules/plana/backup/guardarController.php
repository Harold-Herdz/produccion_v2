<?php
/** @var mysqli $conexion */

require_once("../../includes/conexion.php");

$fecha = $_POST['fecha_plana'] ?? '';
$id_maquina = $_POST['id_maquina'] ?? '';
$id_turno = $_POST['id_turno'] ?? '';
$id_operario = $_POST['id_operario'] ?? '';
$id_referencia = $_POST['id_referencia'] ?? '';
$peso = $_POST['peso_plana'] ?? 0;
$bultos = $_POST['bultos_plana'] ?? 0;
$retal = $_POST['retal_plana'] ?? 0;
$total = $_POST['total_plana'] ?? 0;

$sql = "INSERT INTO PRODUCCION_ROLLO
(
    fecha_plana, 
    id_maquina, 
    id_turno, 
    id_operario,
    id_referencia, 
    peso_plana, 
    bultos_plana,
    retal_plana, 
    total_plana)
VALUES
(
    '$fecha',
    '$id_maquina',
    '$id_turno',
    '$id_operario',
    '$id_referencia',
    '$peso',
    '$bultos',
    '$retal',
    '$total')";

mysqli_query($conexion,$sql);

header("Location: ../views/dashboard.php");
exit;
