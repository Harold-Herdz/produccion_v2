<?php
// Modelo de Register: planilla digital de turno de Sellado

date_default_timezone_set('America/Bogota');

require_once dirname(__DIR__, 2) . '/shared/catalogosModel.php';

/* =================================================
   VÍNCULO PLANILLA ↔ FILAS ('filas' = mapa slot => id_sheet)
================================================= */
// Mapa slot => id_sheet de una planilla
function mapaFilas($planilla){
    $m = json_decode($planilla['filas'] ?? '', true);
    return is_array($m) ? $m : [];
}
// Lista de id_sheet que pertenecen a la planilla
function idSheetsDe($planilla){
    return array_values(mapaFilas($planilla));
}
// Cláusula IN ('S..','S..') escapada; '' si no hay filas
function inIdSheets($conexion, $ids){
    if(empty($ids)){
        return '';
    }
    return implode(',', array_map(function($s) use ($conexion){
        return "'" . $conexion->real_escape_string($s) . "'";
    }, $ids));
}
// id_sheet de borrador (BORR-...), nunca choca con los S##### del Sheet
function idSheetBorrador($planilla, $numMaq, $pos){
    return 'BORR-' . $planilla['id_planilla'] . '-' . $numMaq . '-' . $pos;
}

/* =================================================
   BLOQUES DE TURNO
================================================= */
// Bloques disponibles: etiqueta horaria y código de turno (T001..T003)
function bloquesTurno(){
    return [
        'Día'   => ['horario' => '6am - 2pm',  'codigo' => '001'],
        'Tarde' => ['horario' => '2pm - 10pm', 'codigo' => '002'],
        'Noche' => ['horario' => '10pm - 6am', 'codigo' => '003'],
    ];
}

// Construir el código del turno: S{yyyyMMdd}_T{001|002|003}
function construirCodigoPlanilla($fecha, $bloque){
    $bloques = bloquesTurno();
    $cod = $bloques[$bloque]['codigo'] ?? '000';
    return 'S' . date('Ymd', strtotime($fecha)) . '_T' . $cod;
}

// Validar una fecha 'Y-m-d'; devuelve la fecha normalizada o null
function validarFechaPlanilla($fecha){
    $fecha = trim((string) $fecha);
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if($d && $d->format('Y-m-d') === $fecha){
        return $fecha;
    }
    return null;
}

/* =================================================
   CONSULTAS DEL SOBRE
================================================= */
// Obtener la planilla abierta actual (solo puede haber una a la vez)
function obtenerPlanillaAbierta($conexion){
    $res = $conexion->query("
        SELECT * FROM sellado_sheet
        WHERE estado = 'abierta'
        ORDER BY id_planilla DESC
        LIMIT 1
    ");
    return $res ? $res->fetch_assoc() : null;
}

// Obtener una planilla por su código
function obtenerPlanillaPorCodigo($conexion, $codigo){
    $stmt = $conexion->prepare("SELECT * FROM sellado_sheet WHERE codigo = ? LIMIT 1");
    $stmt->bind_param('s', $codigo);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Crear una nueva planilla de turno; devuelve la fila creada o la existente
function crearPlanilla($conexion, $fecha, $bloque, $id_supervisor, $supervisor_nombre){
    $codigo = construirCodigoPlanilla($fecha, $bloque);
    try {
        $stmt = $conexion->prepare("
            INSERT INTO sellado_sheet (codigo, fecha_planilla, bloque, id_supervisor, supervisor_nombre)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssis', $codigo, $fecha, $bloque, $id_supervisor, $supervisor_nombre);
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        // Clave única: la planilla ya existía (doble clic / carrera). Se recupera abajo.
    }
    return obtenerPlanillaPorCodigo($conexion, $codigo);
}

// Marcar la planilla como finalizada
function finalizarPlanillaSobre($conexion, $codigo, $total){
    $stmt = $conexion->prepare("
        UPDATE sellado_sheet
        SET estado = 'finalizada', total_registros = ?, finalizado_en = NOW()
        WHERE codigo = ? AND estado = 'abierta'
    ");
    $stmt->bind_param('is', $total, $codigo);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

// Guardar la ruta del PDF generado
function guardarRutaPdfPlanilla($conexion, $codigo, $ruta){
    $stmt = $conexion->prepare("UPDATE sellado_sheet SET ruta_pdf = ? WHERE codigo = ?");
    $stmt->bind_param('ss', $ruta, $codigo);
    $stmt->execute();
}

// Cambiar la fecha de una planilla abierta: recalcula el código y actualiza sus filas
function recodificarPlanilla($conexion, $planilla, $fechaNueva){
    if($fechaNueva === $planilla['fecha_planilla']){
        return [$planilla, null];
    }
    $codigoNuevo = construirCodigoPlanilla($fechaNueva, $planilla['bloque']);

    // No pisar una planilla ya finalizada con ese mismo código
    $otra = obtenerPlanillaPorCodigo($conexion, $codigoNuevo);
    if($otra && $otra['id_planilla'] != $planilla['id_planilla']){
        return [$planilla, "El turno {$codigoNuevo} ya existe para esa fecha."];
    }

    // Los id_sheet no cambian: solo se ajusta la fecha de las filas ya guardadas
    $lista = inIdSheets($conexion, idSheetsDe($planilla));
    if($lista !== ''){
        $fe = $conexion->real_escape_string($fechaNueva);
        $conexion->query("UPDATE produccion_sellado SET fecha_sellado = '{$fe}' WHERE id_sheet IN ({$lista})");
    }

    // Actualizar el sobre
    $stmt = $conexion->prepare("UPDATE sellado_sheet SET codigo = ?, fecha_planilla = ? WHERE id_planilla = ?");
    $stmt->bind_param('ssi', $codigoNuevo, $fechaNueva, $planilla['id_planilla']);
    $stmt->execute();

    $planilla['codigo']         = $codigoNuevo;
    $planilla['fecha_planilla'] = $fechaNueva;
    return [$planilla, null];
}

// Cancelar (descartar) una planilla abierta: borra sus filas y el sobre
function cancelarPlanilla($conexion, $planilla){
    $lista = inIdSheets($conexion, idSheetsDe($planilla));
    if($lista !== ''){
        $conexion->query("DELETE FROM produccion_sellado WHERE id_sheet IN ({$lista})");
    }
    $stmt = $conexion->prepare("DELETE FROM sellado_sheet WHERE id_planilla = ? AND estado = 'abierta'");
    $stmt->bind_param('i', $planilla['id_planilla']);
    $stmt->execute();
}

/* =================================================
   CATÁLOGOS DE LA PLANILLA
================================================= */
// Operarios activos para el menú desplegable
function obtenerOperariosActivos($conexion){
    return $conexion->query("
        SELECT id_operario, nombre_operario
        FROM operarios
        WHERE estado = 1
        ORDER BY nombre_operario
    ");
}

// Operarios marcados como supervisor, para el selector al iniciar turno
function obtenerOperariosSupervisores($conexion){
    return $conexion->query("
        SELECT id_operario, nombre_operario
        FROM operarios
        WHERE estado = 1 AND es_supervisor = 1
        ORDER BY nombre_operario
    ");
}

// Un operario-supervisor válido por id (activo y marcado como supervisor)
function obtenerOperarioSupervisorPorId($conexion, $id){
    $id = (int) $id;
    if($id < 1){
        return null;
    }
    $stmt = $conexion->prepare("
        SELECT id_operario, nombre_operario
        FROM operarios
        WHERE id_operario = ? AND estado = 1 AND es_supervisor = 1
        LIMIT 1
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

// Máquinas (con sus referencias), y colores: ver obtenerMaquinasConReferencias()/
// obtenerColoresOrdenados() en shared/catalogosModel.php

/* =================================================
   RESOLUCIÓN DE TURNO Y OPERARIO
================================================= */
// Nombre de turno (catálogo TURNOS) correspondiente al bloque del encabezado de la planilla
// (el bloque ya usa el mismo nombre natural: Día/Tarde/Noche)
function nombreTurnoDesdeBloque($bloque){
    $validos = ['Día' => true, 'Tarde' => true, 'Noche' => true];
    return isset($validos[$bloque]) ? $bloque : null;
}

// Id de turno a partir del bloque del encabezado. TURNOS es un catálogo cerrado de
// 4 valores fijos (Día/Tarde/Noche/18 Horas) llenado a mano; nunca se crea uno aquí.
function obtenerIdTurnoPorBloque($conexion, $bloque){
    $nombreTurno = nombreTurnoDesdeBloque($bloque);
    if($nombreTurno === null){
        return null;
    }
    $stmt = $conexion->prepare("SELECT id_turno FROM turnos WHERE nombre_turno = ? LIMIT 1");
    $stmt->bind_param('s', $nombreTurno);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    return $fila ? (int) $fila['id_turno'] : null;
}

// Id de operario por nombre; lo crea si no existe. Devuelve [id_operario, fue_creado]
function obtenerOperarioIdPorNombre($conexion, $nombre){
    return resolverCatalogoIdONuevo($conexion, 'operarios', 'id_operario', 'nombre_operario', $nombre);
}

/* =================================================
   GUARDADO PROGRESIVO DE LA PLANILLA
================================================= */
// Convertir a entero o NULL si viene vacío
function valorEnteroONulo($valor){
    $valor = trim((string) $valor);
    return ($valor === '') ? null : (int) $valor;
}
// Convertir a decimal o NULL si viene vacío
function valorDecimalONulo($valor){
    $valor = trim((string) $valor);
    if($valor === ''){
        return null;
    }
    return (float) str_replace(',', '.', $valor);
}
// ¿Todos los campos de la entrada están vacíos?
function entradaVacia($ent){
    $campos = ['id_referencia','id_color','x70','x90','x98','p1','p2','p3','p4','p5','obs'];
    foreach($campos as $c){
        if(isset($ent[$c]) && trim((string) $ent[$c]) !== ''){
            return false;
        }
    }
    return true;
}

// Guarda el borrador: cada entrada no vacía se vuelve una fila BORR-... en produccion_sellado
function guardarPlanilla($conexion, $planilla, $maquinas){
    $codigo  = $planilla['codigo'];
    $bloque  = $planilla['bloque'];
    $fecha   = $planilla['fecha_planilla'];
    $avisos  = [];
    $guardados = 0;

    $mapaViejo = mapaFilas($planilla);   // slot => id_sheet actuales
    $mapaNuevo = [];

    // Sentencia de inserción/actualización por entrada
    $sql = "INSERT INTO produccion_sellado
                (id_sheet, fecha_sellado, id_operario, id_maquina, id_referencia, id_referencia_esp, id_color, id_turno, id_jornada,
                 paquetes_x70, paquetes_x90, paquetes_x98,
                 peso_hora1, peso_hora2, peso_hora3, peso_hora4, peso_hora5, obs_sellado)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                id_operario       = VALUES(id_operario),
                id_maquina        = VALUES(id_maquina),
                id_referencia     = VALUES(id_referencia),
                id_referencia_esp = VALUES(id_referencia_esp),
                id_color          = VALUES(id_color),
                id_turno          = VALUES(id_turno),
                id_jornada        = VALUES(id_jornada),
                paquetes_x70      = VALUES(paquetes_x70),
                paquetes_x90      = VALUES(paquetes_x90),
                paquetes_x98      = VALUES(paquetes_x98),
                peso_hora1        = VALUES(peso_hora1),
                peso_hora2        = VALUES(peso_hora2),
                peso_hora3        = VALUES(peso_hora3),
                peso_hora4        = VALUES(peso_hora4),
                peso_hora5        = VALUES(peso_hora5),
                obs_sellado       = VALUES(obs_sellado)";
    $stmt = $conexion->prepare($sql);
    $maquinasEnviadas = []; // solo se borran filas de máquinas que vinieron en el payload

    // Qué máquinas usan Referencias Especiales ("Bolsa Basura") en vez de Referencias normal
    $maquinasEsp = [];
    $resEsp = $conexion->query("SELECT id_maquina, usa_referencias_esp FROM maquinas");
    while($fm = $resEsp->fetch_assoc()){
        $maquinasEsp[(int) $fm['id_maquina']] = (bool) $fm['usa_referencias_esp'];
    }

    // El turno (Día/Tarde/Noche) es el mismo para toda la planilla: se resuelve una sola vez
    $idTurno = obtenerIdTurnoPorBloque($conexion, $bloque);
    if($idTurno === null){
        $avisos[] = "No se encontró el turno «" . (nombreTurnoDesdeBloque($bloque) ?? $bloque) . "» en el catálogo TURNOS.";
    }

    foreach($maquinas as $m){
        $numMaq = (int) ($m['maquina'] ?? 0);
        if($numMaq < 1 || $numMaq > 17){
            continue;
        }
        // id_maquina es el id real del catálogo (no coincide con el número 1-17 visible);
        // es el que se guarda como FK. $numMaq solo se usa para slots/etiquetas/BORR-.
        $idMaquina = (int) ($m['id_maquina'] ?? 0);
        if($idMaquina < 1){
            continue;
        }
        $maquinasEnviadas[$numMaq] = true;
        $maqEtiqueta = str_pad($numMaq, 2, '0', STR_PAD_LEFT);

        // Resolver operario (el select se convierte en texto libre al elegir "Otro")
        [$idOperario, $avisoOp] = resolverValorCatalogo(
            $conexion, 'operarios', 'id_operario', 'nombre_operario',
            $m['id_operario'] ?? '', 'nuevo operario', "Máquina {$maqEtiqueta}", 'sellado'
        );
        if($avisoOp){ $avisos[] = $avisoOp; }

        // Jornada de la máquina: catálogo cerrado de 2 valores (8 Horas/12 Horas).
        // Cualquier otro texto (horario suelto, "Otro" sin completar, etc.) se
        // normaliza siempre a "8 Horas"; nunca se crea una jornada nueva.
        $idJornada = resolverIdJornada($conexion, $m['jornada'] ?? '');
        if($idJornada === null){
            $avisos[] = "No se encontró la jornada «" . normalizarJornada($m['jornada'] ?? '') . "» en el catálogo JORNADAS.";
        }

        // Recorrer las entradas de la máquina (máximo 6); la posición cuenta solo las no vacías
        $entradas = $m['entradas'] ?? [];
        $pos = 0;
        foreach(array_values($entradas) as $e => $ent){
            if($e > 5){
                break;
            }
            if(entradaVacia($ent)){
                continue;
            }
            $pos++;
            $slot = $numMaq . '-' . $pos;

            // id_sheet de borrador (BORR-...); estable por slot, se envía a REGISTROS al finalizar
            $idSheet = $mapaViejo[$slot] ?? idSheetBorrador($planilla, $numMaq, $pos);
            $mapaNuevo[$slot] = $idSheet;

            // Máquinas "Bolsa Basura" guardan en id_referencia_esp (catálogo REFERENCIAS_ESP);
            // el resto guarda en id_referencia (catálogo REFERENCIAS). Nunca ambos a la vez.
            if($maquinasEsp[$idMaquina] ?? false){
                [$idReferenciaEsp, $avisoRef] = resolverValorCatalogo(
                    $conexion, 'referencias_esp', 'id_referencia_esp', 'nombre_referencia_esp',
                    $ent['id_referencia'] ?? '', 'nueva referencia especial', "Máquina {$maqEtiqueta}", 'sellado'
                );
                $idReferencia = null;
            } else {
                [$idReferencia, $avisoRef] = resolverValorCatalogo(
                    $conexion, 'referencias', 'id_referencia', 'nombre_referencia',
                    $ent['id_referencia'] ?? '', 'nueva referencia', "Máquina {$maqEtiqueta}", 'sellado'
                );
                $idReferenciaEsp = null;
            }
            if($avisoRef){ $avisos[] = $avisoRef; }
            [$idColor, $avisoColor] = resolverValorCatalogo(
                $conexion, 'colores', 'id_color', 'nombre_color',
                $ent['id_color'] ?? '', 'nuevo color', "Máquina {$maqEtiqueta}", 'sellado'
            );
            if($avisoColor){ $avisos[] = $avisoColor; }
            $x70 = valorEnteroONulo($ent['x70'] ?? '');
            $x90 = valorEnteroONulo($ent['x90'] ?? '');
            $x98 = valorEnteroONulo($ent['x98'] ?? '');
            $p1  = valorDecimalONulo($ent['p1'] ?? '');
            $p2  = valorDecimalONulo($ent['p2'] ?? '');
            $p3  = valorDecimalONulo($ent['p3'] ?? '');
            $p4  = valorDecimalONulo($ent['p4'] ?? '');
            $p5  = valorDecimalONulo($ent['p5'] ?? '');
            $obs = trim($ent['obs'] ?? '');

            $stmt->bind_param(
                'ssiiiiiiiiiiddddds',
                $idSheet, $fecha, $idOperario, $idMaquina, $idReferencia, $idReferenciaEsp, $idColor, $idTurno, $idJornada,
                $x70, $x90, $x98, $p1, $p2, $p3, $p4, $p5, $obs
            );
            $stmt->execute();
            $guardados++;
        }
    }

    // Conservar los slots de máquinas ausentes del payload (protege contra payloads parciales:
    // si una máquina no vino en $maquinas, sus filas ni se borran ni se pierden del mapa)
    foreach($mapaViejo as $slot => $idSheet){
        $numSlot = (int) strtok($slot, '-');
        if(!isset($maquinasEnviadas[$numSlot]) && !isset($mapaNuevo[$slot])){
            $mapaNuevo[$slot] = $idSheet;
        }
    }

    // Borrar las filas cuyo slot ya no existe (entrada vaciada o eliminada)
    $borrar = array_values(array_diff(array_values($mapaViejo), array_values($mapaNuevo)));
    $listaBorrar = inIdSheets($conexion, $borrar);
    if($listaBorrar !== ''){
        $conexion->query("DELETE FROM produccion_sellado WHERE id_sheet IN ({$listaBorrar})");
    }

    // Guardar el mapa actualizado en el sobre
    $json = json_encode($mapaNuevo);
    $upd = $conexion->prepare("UPDATE sellado_sheet SET filas = ? WHERE codigo = ?");
    $upd->bind_param('ss', $json, $codigo);
    $upd->execute();

    return [
        'guardados' => $guardados,
        'avisos'    => array_values(array_unique($avisos)),
    ];
}

/* =================================================
   RECUPERACIÓN DE LA PLANILLA
================================================= */
// Contar las filas ya guardadas de una planilla (por su código)
function contarRegistrosPlanilla($conexion, $codigo){
    $p = obtenerPlanillaPorCodigo($conexion, $codigo);
    return $p ? count(idSheetsDe($p)) : 0;
}

// Reconstruir la planilla guardada como estructura por máquina para la vista / JS
function obtenerPlanillaEstructurada($conexion, $planilla){
    $lista = inIdSheets($conexion, idSheetsDe($planilla));
    if($lista === ''){
        return [];
    }
    $res = $conexion->query("
        SELECT s.id_sheet, s.id_maquina, s.id_operario, s.id_referencia, s.id_referencia_esp, s.id_color,
               CAST(REGEXP_SUBSTR(m.nombre_maquina, '[0-9]+') AS UNSIGNED) AS numero_maquina,
               s.paquetes_x70, s.paquetes_x90, s.paquetes_x98,
               s.peso_hora1, s.peso_hora2, s.peso_hora3, s.peso_hora4, s.peso_hora5,
               s.obs_sellado, j.nombre_jornada AS jornada
        FROM produccion_sellado s
        LEFT JOIN maquinas m ON s.id_maquina = m.id_maquina
        LEFT JOIN jornadas j ON s.id_jornada = j.id_jornada
        WHERE s.id_sheet IN ({$lista})
        ORDER BY numero_maquina, s.id
    ");

    $maquinas = [];
    while($fila = $res->fetch_assoc()){
        $num = (int) $fila['numero_maquina'];
        if(!isset($maquinas[$num])){
            $maquinas[$num] = [
                'id_operario' => $fila['id_operario'],
                'jornada'     => $fila['jornada'],
                'entradas'    => [],
            ];
        }
        $maquinas[$num]['entradas'][] = [
            'id_referencia'     => $fila['id_referencia'],
            'id_referencia_esp' => $fila['id_referencia_esp'],
            'id_color'          => $fila['id_color'],
            'x70'           => $fila['paquetes_x70'],
            'x90'           => $fila['paquetes_x90'],
            'x98'           => $fila['paquetes_x98'],
            'p1'            => $fila['peso_hora1'],
            'p2'            => $fila['peso_hora2'],
            'p3'            => $fila['peso_hora3'],
            'p4'            => $fila['peso_hora4'],
            'p5'            => $fila['peso_hora5'],
            'obs'           => $fila['obs_sellado'],
        ];
    }
    return $maquinas;
}

// Datos completos de las entradas (con nombres) para el PDF
function obtenerEntradasPlanillaPdf($conexion, $codigo){
    $p = obtenerPlanillaPorCodigo($conexion, $codigo);
    $lista = inIdSheets($conexion, $p ? idSheetsDe($p) : []);
    $filtro = ($lista === '') ? '1 = 0' : "s.id_sheet IN ({$lista})";
    return $conexion->query("
        SELECT s.id_maquina, m.nombre_maquina,
               CAST(REGEXP_SUBSTR(m.nombre_maquina, '[0-9]+') AS UNSIGNED) AS numero_maquina,
               o.nombre_operario, o.verificado AS operario_verificado, j.nombre_jornada AS jornada,
               COALESCE(r.nombre_referencia, re.nombre_referencia_esp) AS nombre_referencia, c.nombre_color,
               s.paquetes_x70, s.paquetes_x90, s.paquetes_x98, s.paquetes_total,
               s.peso_hora1, s.peso_hora2, s.peso_hora3, s.peso_hora4, s.peso_hora5,
               s.obs_sellado
        FROM produccion_sellado s
        LEFT JOIN maquinas m         ON s.id_maquina        = m.id_maquina
        LEFT JOIN operarios o        ON s.id_operario       = o.id_operario
        LEFT JOIN referencias r      ON s.id_referencia     = r.id_referencia
        LEFT JOIN referencias_esp re ON s.id_referencia_esp = re.id_referencia_esp
        LEFT JOIN colores c          ON s.id_color          = c.id_color
        LEFT JOIN jornadas j         ON s.id_jornada        = j.id_jornada
        WHERE {$filtro}
        ORDER BY numero_maquina, s.id
    ");
}

/* =================================================
   ENVÍO A GOOGLE SHEETS (hoja REGISTROS / LOGS)
================================================= */
// Número o cadena vacía (para celdas numéricas del Sheet)
function numSheet($v){
    return ($v === null || $v === '') ? '' : $v + 0;
}

// Filas para REGISTROS: 17 columnas B..R por entrada (A la genera la fórmula del Sheet)
function construirFilasRegistros($conexion, $planilla, $maquinasPayload){
    // Otro operario por número de máquina (id_operario no numérico = texto libre escrito a mano)
    $otros = [];
    foreach($maquinasPayload as $m){
        $n = (int) ($m['maquina'] ?? 0);
        $valor = trim((string) ($m['id_operario'] ?? ''));
        if($valor !== '' && $valor !== 'otro' && !is_numeric($valor)){
            $otros[$n] = $valor;
        }
    }

    $bloques  = bloquesTurno();
    $horario  = $bloques[$planilla['bloque']]['horario'] ?? $planilla['bloque'];
    $fechaIso = $planilla['fecha_planilla'];

    $res   = obtenerEntradasPlanillaPdf($conexion, $planilla['codigo']);
    $filas = [];
    while($f = $res->fetch_assoc()){
        $num = (int) $f['numero_maquina'];
        $filas[] = [
            $fechaIso,
            $horario,
            $f['nombre_maquina'] ?? ('Máquina ' . str_pad($num, 2, '0', STR_PAD_LEFT)),
            $f['nombre_operario'] ?? '',
            $f['jornada'] ?? '',
            $f['nombre_referencia'] ?? '',
            $f['nombre_color'] ?? '',
            numSheet($f['paquetes_x70']),
            numSheet($f['paquetes_x90']),
            numSheet($f['paquetes_x98']),
            numSheet($f['peso_hora1']),
            numSheet($f['peso_hora2']),
            numSheet($f['peso_hora3']),
            numSheet($f['peso_hora4']),
            numSheet($f['peso_hora5']),
            $f['obs_sellado'] ?? '',
            $otros[$num] ?? '',
        ];
    }
    return $filas;
}

// Fila para la hoja LOGS
function construirLogPlanilla($planilla, $numRegistros){
    $bloques = bloquesTurno();
    $horario = $bloques[$planilla['bloque']]['horario'] ?? $planilla['bloque'];
    return [
        $planilla['codigo'],                 // ID DEL TURNO (S20260701_T001)
        $planilla['fecha_planilla'],          // FECHA
        $horario,                             // TURNO
        $planilla['supervisor_nombre'] ?? '', // SUPERVISOR
        $planilla['creado_en'] ?? date('Y-m-d H:i:s'), // INICIO (apertura del turno)
        date('Y-m-d H:i:s'),                  // FIN (finalización)
        $numRegistros,                        // REGISTROS
        'COMPLETADO',                         // ESTADO
    ];
}

// Borrar las filas de borrador de una planilla (deja el sobre)
function borrarFilasPlanilla($conexion, $planilla){
    $lista = inIdSheets($conexion, idSheetsDe($planilla));
    if($lista !== ''){
        $conexion->query("DELETE FROM produccion_sellado WHERE id_sheet IN ({$lista})");
    }
}
