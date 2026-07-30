<?php
/** @var mysqli $conexion */

// Importar conexion.php
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar produccionModel.php
require_once dirname(__DIR__) . '/models/produccionModel.php';

// Obtener ID del registro a actualizar
$id = $_POST['id'];

// Recopilar datos del formulario
$datos = [
    'fecha' => $_POST['fecha_rollo'],

    'id_maquina' => $_POST['id_maquina'],

    'id_referencia' => $_POST['id_referencia'],

    'id_color' => $_POST['id_color'],

    'peso_rollo' => $_POST['peso_rollo'],

    'retal_rollo' => $_POST['retal_rollo'],

    'total_rollo' => $_POST['total_rollo']
];

// Actualizar registro 
actualizarProduccion($conexion, $id, $datos);

// Redirigir al Lista
header("Location: " . BASE_URL . "/modules/rollo/views/lista.php");
exit;