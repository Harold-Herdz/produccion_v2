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
    'fecha' => $_POST['fecha_paq'],

    'id_operario' => $_POST['id_operario'],

    'id_maquina' => $_POST['id_maquina'],

    'id_referencia' => $_POST['id_referencia'],

    'id_color' => $_POST['id_color'],

    'id_turno' => $_POST['id_turno'],

    'paq_x70' => $_POST['paquetes_x70'],

    'paq_x90' => $_POST['paquetes_x90'],

    'paq_x98' => $_POST['paquetes_x98'],

    'paquetes' => $_POST['paquetes_total'],

    'observaciones' => $_POST['observaciones_paq']
];

// Actualizar registro 
actualizarProduccion($conexion, $id, $datos);

// Redirigir al Lista
header("Location: " . BASE_URL . "/modules/sellado/views/lista.php");
exit;