<?php
/** @var mysqli $conexion */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/conexion.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/models/registerModel.php';
require_once dirname(__DIR__) . '/spreadsheet/appsScript.php';
require_once dirname(__DIR__) . '/spreadsheet/pdfSpreadsheet.php';

$hoy = date('Y-m-d');
$rutaRegister = BASE_URL . '/modules/mezclas/views/register.php';

/* =================================================
   INICIAR PLANILLA (PRG)
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'iniciar'){
    $fecha = validarFechaMezcla($_POST['fecha'] ?? '') ?: $hoy;
    $idMaquina = (int) ($_POST['id_maquina'] ?? 0);

    $maquina = null;
    foreach(obtenerMaquinasDeArea($conexion, 'mezclas') as $m){ if((int) $m['id_maquina'] === $idMaquina){ $maquina = $m; } }

    if(!$maquina){
        header('Location: ' . $rutaRegister . '?reg_error=' . urlencode('Completa la fecha.') . '&id_maquina=' . $idMaquina);
        exit;
    }

    // Varias mezclas el mismo día+máquina son válidas: solo se retoma si hay
    // una SIN finalizar; si no, siempre se crea una nueva.
    $codigo = construirCodigoMezcla($fecha);
    $existente = buscarPlanillaAbiertaMezcla($conexion, $codigo, $idMaquina);
    $planilla = $existente ?: crearPlanillaMezcla($conexion, $codigo, $fecha, $idMaquina);
    header('Location: ' . $rutaRegister . '?id=' . $planilla['id_planilla']);
    exit;
}

/* =================================================
   CANCELAR PLANILLA (PRG)
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar'){
    cancelarPlanillaMezcla($conexion, (int) ($_POST['id'] ?? 0));
    $idMaquinaCancelada = (int) ($_POST['id_maquina'] ?? 0);
    header('Location: ' . $rutaRegister . ($idMaquinaCancelada ? '?id_maquina=' . $idMaquinaCancelada : ''));
    exit;
}

/* =================================================
   FINALIZAR MEZCLA (PRG)
================================================= */
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'finalizar'){
    $id = (int) ($_POST['id'] ?? 0);
    $planilla = obtenerPlanillaMezcla($conexion, $id);
    $rutaPlanilla = $rutaRegister . '?id=' . $id;

    if(!$planilla || $planilla['estado'] !== 'abierta'){
        header('Location: ' . $rutaRegister);
        exit;
    }

    // Operario: id o texto Otro
    $aviso = null;
    $valorOperario = trim((string) ($_POST['id_operario'] ?? ''));
    if($valorOperario === ''){
        header('Location: ' . $rutaPlanilla . '&reg_error=' . urlencode('Selecciona un operario.'));
        exit;
    }
    if(is_numeric($valorOperario)){
        $idOperario = (int) $valorOperario;
        $nombreOperario = nombreCatalogoMezcla($conexion, 'operarios', 'id_operario', 'nombre_operario', $idOperario);
    } else {
        if(!nombrePropioValido($valorOperario)){
            header('Location: ' . $rutaPlanilla . '&reg_error=' . urlencode('El nombre del operario solo puede tener letras.'));
            exit;
        }
        $valorOperario = capitalizarNombre($valorOperario);
        [$idOperario, $fueCreado] = resolverCatalogoIdONuevo($conexion, 'operarios', 'id_operario', 'nombre_operario', $valorOperario);
        $nombreOperario = $valorOperario;
        if($fueCreado){
            $aviso = "Se agregó «{$valorOperario}» como nuevo operario. Falta verificarlo en Catálogos.";
            registrarCatalogoPendiente($conexion, 'operarios', $idOperario, $valorOperario, 'nuevo operario', '', 'mezclas');
        }
    }

    // Referencia: id o texto Otro (opcional, puede quedar vacía)
    [$idReferencia, $avisoReferencia] = resolverValorCatalogo(
        $conexion, 'referencias', 'id_referencia', 'nombre_referencia',
        $_POST['id_referencia'] ?? '', 'nueva referencia', '', 'mezclas'
    );
    $nombreReferencia = $idReferencia
        ? nombreCatalogoMezcla($conexion, 'referencias', 'id_referencia', 'nombre_referencia', $idReferencia)
        : '';
    if($avisoReferencia){
        $aviso = $aviso ? $aviso . ' ' . $avisoReferencia : $avisoReferencia;
    }

    // Columnas numéricas de las 2 tablas
    $datos = [
        'fecha' => $planilla['fecha_planilla'], 'id_maquina' => $planilla['id_maquina'],
        'id_operario' => $idOperario, 'id_referencia' => $idReferencia,
        'observaciones' => trim((string) ($_POST['observaciones'] ?? '')),
    ];
    foreach(array_merge(array_keys(columnasTabla1Mezcla()), array_keys(columnasTabla2Mezcla()), array_keys(columnasTabla3Mezcla())) as $col){
        $valor = valorMezcla($_POST[$col] ?? '');
        if($valor === false){
            header('Location: ' . $rutaPlanilla . '&reg_error=' . urlencode('Hay un valor inválido en ' . strtoupper($col) . '.'));
            exit;
        }
        $datos[$col] = $valor;
    }

    // Guardar primero: el PDF del día se arma leyendo todo lo ya guardado
    $idSheet = idSheetMezcla($planilla['fecha_planilla'], $planilla['nombre_maquina'], $planilla['id_planilla']);
    guardarMezcla($conexion, $idSheet, $datos);

    // PDF del día+máquina completo (puede traer más de una mezcla; solo columnas llenas)
    $mezclasDelDia = obtenerMezclasDelDiaMezcla($conexion, $planilla['fecha_planilla'], $planilla['id_maquina']);
    foreach($mezclasDelDia as &$m){
        $m['operario'] = $m['nombre_operario'];
        $m['referencia'] = $m['nombre_referencia'];
    }
    unset($m);
    $pdfBytes = generarPdfMezcla($planilla['fecha_planilla'], $planilla['nombre_maquina'], $mezclasDelDia);

    // Google Sheets + Drive (si ya está configurado el Apps Script)
    $rutaPdf = null;
    if(appScriptConfiguradoMezcla()){
        // Orden exacto de la hoja REGISTROS: ...LADRILLO R, ORIGINAL B...LINEAL, DESHIDRATANTE, OBSERVACIONES, P. BLANCO...
        $columnasX25 = columnasX25Mezcla();
        $fila = [$planilla['fecha_planilla'], $planilla['nombre_maquina'], $nombreOperario, $nombreReferencia];
        foreach(array_merge(array_keys(columnasTabla1Mezcla()), array_keys(columnasTabla2Mezcla())) as $col){
            if($datos[$col] === null || $datos[$col] === ''){
                $fila[] = '';
            } elseif(in_array($col, $columnasX25, true)){
                $fila[] = formatoNumeroMezcla($datos[$col]) . 'x25'; // texto completo, no solo el número
            } else {
                $fila[] = $datos[$col]; // deshidratante: kg, número tal cual
            }
        }
        $fila[] = $datos['observaciones'];
        foreach(array_keys(columnasTabla3Mezcla()) as $col){
            $fila[] = $datos[$col] ?? '';
        }

        $respuesta = enviarAppScriptMezcla([
            'accion'       => 'finalizar',
            'id_solicitud' => $idSheet, // evita duplicar la fila si el 1er intento sí se guardó pero la respuesta se perdió
            'registros'    => [$fila],
            'log'       => [
                'id_dia'    => $planilla['codigo'],
                'fecha'     => $planilla['fecha_planilla'],
                'maquina'   => $planilla['nombre_maquina'],
                'inicio'    => $planilla['creado_en'],
                'fin'       => date('Y-m-d H:i:s'),
                'registros' => count($mezclasDelDia), // total acumulado del día en esta máquina
            ],
            'pdf' => [
                'nombre'  => nombrePdfMezcla($planilla['fecha_planilla'], $planilla['nombre_maquina']),
                'maquina' => $planilla['nombre_maquina'],
                'mes'     => date('m-Y', strtotime($planilla['fecha_planilla'])),
                'base64'  => base64_encode($pdfBytes),
            ],
        ]);
        if(!empty($respuesta['ok'])){
            $rutaPdf = $respuesta['pdf_url'] ?? null;
        }
    }

    finalizarPlanillaMezcla($conexion, $id, $rutaPdf);
    // Vuelve a Fecha (misma máquina): se sigue registrando ahí mismo
    $mensaje = 'Mezcla registrada correctamente' . ($aviso ? ' · ' . $aviso : '');
    header('Location: ' . $rutaRegister . '?id_maquina=' . $planilla['id_maquina'] . '&aviso=' . urlencode($mensaje));
    exit;
}

/* =================================================
   ESTADO ACTUAL
================================================= */
$planilla = null;
if(isset($_GET['id'])){
    $planilla = obtenerPlanillaMezcla($conexion, (int) $_GET['id']);
    if($planilla && $planilla['estado'] !== 'abierta'){
        $planilla = null; // ya finalizada
    }
}

if(!$planilla){
    // Sin planilla: primero elegir máquina, luego fecha
    $maquinas = obtenerMaquinasDeArea($conexion, 'mezclas');
    $idMaquina = (int) ($_GET['id_maquina'] ?? 0);
    $maquinaSeleccionada = null;
    foreach($maquinas as $m){ if((int) $m['id_maquina'] === $idMaquina){ $maquinaSeleccionada = $m; } }

    $abiertas = planillasAbiertasMezcla($conexion, $maquinaSeleccionada ? $idMaquina : null);
    return;
}

// Catálogos de la planilla
$operarios   = obtenerOperariosDeArea($conexion, 'mezclas');
$referencias = referenciasDeMaquinaMezcla($conexion, $planilla['id_maquina']);
