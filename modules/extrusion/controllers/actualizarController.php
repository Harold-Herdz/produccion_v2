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
    'fecha' => $_POST['fecha_extrusion'],

    'id_maquina' => $_POST['id_maquina'],

    'id_turno_ext' => $_POST['id_turno_ext'],

    'id_operador_ext' => $_POST['id_operador_ext'],

    'id_referencia' => $_POST['id_referencia'],

    'id_color' => $_POST['id_color'],

    'lamina_p' => $_POST['lamina_p'],

    'rollos_ext' => $_POST['rollos_extrusion'],
    
    'total_ext' => $_POST['total_extrusion']
];

// Actualizar registro 
actualizarProduccion($conexion, $id, $datos);

// Redirigir al Lista
header("Location: " . BASE_URL . "/modules/extrusion/views/lista.php");
exit;
